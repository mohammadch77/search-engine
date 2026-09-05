<?php

namespace App\Services;

use App\Models\CrawlLog;
use App\Models\CrawlQueue;
use App\Models\Domain;
use App\Models\Link;
use App\Models\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrawlManager
{
    public function __construct(
        protected CrawlerService $crawler,
        protected HtmlParser $parser,
        protected RobotsTxtParser $robots,
    ) {
    }

    public function addDomain(string $url): Domain
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            throw new \InvalidArgumentException("Invalid URL: {$url}");
        }

        $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'https';
        $baseUrl = "{$scheme}://{$host}";

        $domain = Domain::firstOrCreate(
            ['name' => $host],
            ['base_url' => $baseUrl, 'status' => 'active']
        );

        if ($domain->wasRecentlyCreated || $domain->robots_txt === null) {
            $robotsTxt = $this->robots->fetch($baseUrl);
            $crawlDelay = $this->robots->getCrawlDelay($robotsTxt);

            $domain->robots_txt = $robotsTxt;
            if ($crawlDelay !== null) {
                $domain->crawl_delay_ms = $crawlDelay;
            }
            $domain->save();
        }

        $this->enqueueUrl($domain, $url, depth: 0, priority: 10);

        return $domain;
    }

    protected function enqueueUrl(Domain $domain, string $url, int $depth, int $priority = 0): ?CrawlQueue
    {
        $urlHash = hash('sha256', $url);

        if (CrawlQueue::where('url_hash', $urlHash)->exists()) {
            return null;
        }

        if (Page::where('url_hash', $urlHash)->exists()) {
            return null;
        }

        return CrawlQueue::create([
            'domain_id' => $domain->id,
            'url' => $url,
            'url_hash' => $urlHash,
            'priority' => $priority,
            'depth' => $depth,
            'status' => 'pending',
        ]);
    }

    /**
     * Process up to $limit pending queue items.
     *
     * @return array{processed: int, succeeded: int, failed: int}
     */
    public function processQueue(int $limit = 100): array
    {
        $processed = 0;
        $succeeded = 0;
        $failed = 0;

        for ($i = 0; $i < $limit; $i++) {
            $item = CrawlQueue::pending()->nextByPriority()->first();

            if (! $item) {
                break;
            }

            $processed++;

            if ($this->processQueueItem($item)) {
                $succeeded++;
            } else {
                $failed++;
            }
        }

        return ['processed' => $processed, 'succeeded' => $succeeded, 'failed' => $failed];
    }

    public function processQueueItem(CrawlQueue $item): bool
    {
        $item->update(['status' => 'processing', 'last_attempt_at' => now(), 'attempts' => $item->attempts + 1]);

        $domain = $item->domain;

        if (! $domain || $domain->status !== 'active') {
            $item->update(['status' => 'failed']);

            return false;
        }

        if (! $this->robots->isAllowed($domain->robots_txt, $item->url)) {
            $item->update(['status' => 'failed']);
            $this->log($domain, null, $item->url, null, null, null, 'Blocked by robots.txt');

            return false;
        }

        $result = $this->crawler->fetch($item->url, $domain->crawl_delay_ms ?? 1000);

        if ($result['error'] !== null || $result['status'] === null) {
            return $this->handleFailure($item, $domain, $result);
        }

        if ($result['status'] >= 400) {
            $this->log($domain, null, $item->url, $result['status'], $result['response_time_ms'], null, "HTTP {$result['status']}");

            return $this->handleFailure($item, $domain, $result);
        }

        $contentType = $result['content_type'] ?? '';
        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            $item->update(['status' => 'done']);
            $this->log($domain, null, $item->url, $result['status'], $result['response_time_ms'], strlen($result['body'] ?? ''), 'Skipped non-HTML content');

            return true;
        }

        $parsed = $this->parser->parse($result['body'] ?? '', $item->url);

        $page = $this->savePage($domain, $item, $result, $parsed);

        $this->saveLinks($domain, $page, $item, $parsed['links']);

        $domain->increment('pages_count');
        $domain->update(['last_crawled_at' => now()]);

        $item->update(['status' => 'done']);

        $this->log($domain, $page, $item->url, $result['status'], $result['response_time_ms'], strlen($result['body'] ?? ''), null);

        return true;
    }

    protected function handleFailure(CrawlQueue $item, Domain $domain, array $result): bool
    {
        $status = $item->attempts >= $item->max_attempts ? 'failed' : 'pending';
        $item->update(['status' => $status]);

        $this->log($domain, null, $item->url, $result['status'], $result['response_time_ms'], null, $result['error'] ?? "HTTP {$result['status']}");

        return false;
    }

    protected function savePage(Domain $domain, CrawlQueue $item, array $result, array $parsed): Page
    {
        $urlHash = $item->url_hash;
        $contentHash = hash('sha256', $parsed['content_text']);

        return Page::updateOrCreate(
            ['url_hash' => $urlHash],
            [
                'domain_id' => $domain->id,
                'url' => $item->url,
                'title' => $parsed['title'] ? mb_substr($parsed['title'], 0, 500) : null,
                'meta_description' => $parsed['meta_description'],
                'meta_keywords' => $parsed['meta_keywords'],
                'content_raw' => $result['body'],
                'content_text' => $parsed['content_text'],
                'content_hash' => $contentHash,
                'http_status' => $result['status'],
                'content_type' => $result['content_type'],
                'language' => $parsed['language'],
                'word_count' => $parsed['word_count'],
                'depth' => $item->depth,
                'status' => 'indexed',
                'crawled_at' => now(),
                'indexed_at' => now(),
            ]
        );
    }

    protected function saveLinks(Domain $domain, Page $page, CrawlQueue $item, array $links): void
    {
        foreach ($links as $link) {
            $targetUrlHash = hash('sha256', $link['url']);
            $targetPage = Page::where('url_hash', $targetUrlHash)->first();

            Link::create([
                'source_page_id' => $page->id,
                'target_page_id' => $targetPage?->id,
                'target_url' => $link['url'],
                'anchor_text' => $link['anchor_text'] ? mb_substr($link['anchor_text'], 0, 500) : null,
                'is_external' => $link['is_external'],
            ]);

            if (! $link['is_external'] && $item->depth < $domain->max_depth) {
                $this->enqueueUrl($domain, $link['url'], $item->depth + 1);
            }
        }
    }

    protected function log(Domain $domain, ?Page $page, string $url, ?int $statusCode, ?int $responseTimeMs, ?int $sizeBytes, ?string $error): void
    {
        try {
            CrawlLog::create([
                'domain_id' => $domain->id,
                'page_id' => $page?->id,
                'url' => $url,
                'status_code' => $statusCode,
                'response_time_ms' => $responseTimeMs,
                'content_size_bytes' => $sizeBytes,
                'error_message' => $error,
                'crawled_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to write crawl log: '.$e->getMessage());
        }
    }
}
