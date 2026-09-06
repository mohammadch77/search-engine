<?php

namespace App\Services;

use App\Models\CrawlLog;
use App\Models\CrawlQueue;
use App\Models\Domain;
use App\Models\Link;
use App\Models\Page;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
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

    /**
     * @param  bool  $fetchRobots  Fetch robots.txt inline (blocking). Set to false for
     *                             auto-discovered domains so it doesn't stall the worker
     *                             that found the link — it's fetched lazily on first crawl.
     */
    public function addDomain(string $url, int $priority = 10, bool $fetchRobots = true): Domain
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            throw new \InvalidArgumentException("Invalid URL: {$url}");
        }

        $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'https';
        $baseUrl = "{$scheme}://{$host}";

        try {
            $domain = Domain::firstOrCreate(
                ['name' => $host],
                [
                    'base_url' => $baseUrl,
                    'status' => 'active',
                    'max_depth' => config('crawler.max_depth', 10),
                    'crawl_delay_ms' => config('crawler.crawl_delay_ms', 200),
                ]
            );
        } catch (QueryException $e) {
            // Two workers raced to discover the same new domain; the loser
            // just re-reads what the winner inserted.
            $domain = Domain::where('name', $host)->firstOrFail();
        }

        if ($fetchRobots && ! $domain->robots_checked) {
            $this->fetchRobotsFor($domain, $baseUrl);
        }

        $this->enqueueUrl($domain, $url, depth: 0, priority: $priority);

        return $domain;
    }

    protected function fetchRobotsFor(Domain $domain, ?string $baseUrl = null): void
    {
        $robotsTxt = $this->robots->fetch($baseUrl ?? $domain->base_url);
        $crawlDelay = $this->robots->getCrawlDelay($robotsTxt);

        $domain->robots_txt = $robotsTxt;
        $domain->robots_checked = true;
        if ($crawlDelay !== null) {
            $domain->crawl_delay_ms = $crawlDelay;
        }
        $domain->save();
    }

    /**
     * Atomically claim and return the next pending queue item, or null if
     * none is available. Safe for many worker processes to call concurrently.
     */
    public function claimNext(string $workerId): ?CrawlQueue
    {
        return DB::transaction(function () use ($workerId) {
            $item = CrawlQueue::claimable()->nextByPriority()->lock('for update skip locked')->first();

            if (! $item) {
                return null;
            }

            $item->update([
                'status' => 'processing',
                'locked_by' => $workerId,
                'last_attempt_at' => now(),
                'attempts' => $item->attempts + 1,
            ]);

            return $item;
        });
    }

    protected function enqueueUrl(Domain $domain, string $url, int $depth, int $priority = 0): void
    {
        $urlHash = hash('sha256', $url);

        if (Page::where('url_hash', $urlHash)->exists()) {
            return;
        }

        // insertOrIgnore silently skips the row if url_hash already exists
        // (unique index) instead of throwing a duplicate-key exception —
        // safe under many concurrent workers racing to enqueue the same link.
        CrawlQueue::query()->insertOrIgnore([[
            'domain_id' => $domain->id,
            'url' => $url,
            'url_hash' => $urlHash,
            'priority' => $priority,
            'depth' => $depth,
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]]);
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

    public function processQueueItem(CrawlQueue $item, bool $alreadyClaimed = false): bool
    {
        if (! $alreadyClaimed) {
            $item->update(['status' => 'processing', 'last_attempt_at' => now(), 'attempts' => $item->attempts + 1]);
        }

        $domain = $this->prepareDomainForItem($item);

        if (! $domain) {
            return false;
        }

        $result = $this->crawler->fetch($item->url, $domain->crawl_delay_ms ?? config('crawler.crawl_delay_ms', 200));

        return $this->applyFetchResult($item, $domain, $result);
    }

    /**
     * Atomically claim up to $batchSize pending items for concurrent fetching.
     *
     * @return CrawlQueue[]
     */
    public function claimBatch(string $workerId, int $batchSize): array
    {
        $items = [];

        for ($i = 0; $i < $batchSize; $i++) {
            $item = $this->claimNext($workerId);

            if (! $item) {
                break;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Fetch a batch of claimed items concurrently (bounded by
     * crawler.fetch_concurrency) and process each result as it completes.
     *
     * @param  CrawlQueue[]  $items
     */
    public function processBatch(array $items): void
    {
        $domains = [];
        $urlDelayPairs = [];
        $itemsByUrl = [];

        foreach ($items as $item) {
            try {
                $domain = $this->prepareDomainForItem($item);
            } catch (\Throwable $e) {
                $item->update(['status' => 'failed', 'locked_by' => null]);

                continue;
            }

            if (! $domain) {
                continue;
            }

            $domains[$item->id] = $domain;
            $urlDelayPairs[$item->url] = $domain->crawl_delay_ms ?? config('crawler.crawl_delay_ms', 200);
            $itemsByUrl[$item->url] = $item;
        }

        if ($urlDelayPairs === []) {
            return;
        }

        $results = $this->crawler->fetchConcurrently($urlDelayPairs);

        foreach ($itemsByUrl as $url => $item) {
            try {
                $this->applyFetchResult($item, $domains[$item->id], $results[$url] ?? [
                    'status' => null,
                    'body' => null,
                    'response_time_ms' => null,
                    'content_type' => null,
                    'error' => 'No result returned from pool',
                    'skipped_reason' => null,
                ]);
            } catch (\Throwable $e) {
                $item->update(['status' => 'failed', 'locked_by' => null]);
                Log::warning("Failed processing {$url}: ".$e->getMessage());
            }
        }
    }

    /**
     * Ensure the item's domain is active and allowed by robots.txt. Marks the
     * item failed and returns null when it can't be crawled.
     */
    protected function prepareDomainForItem(CrawlQueue $item): ?Domain
    {
        $domain = $item->domain;

        if (! $domain || $domain->status !== 'active') {
            $item->update(['status' => 'failed', 'locked_by' => null]);

            return null;
        }

        if (! $domain->robots_checked) {
            $this->fetchRobotsFor($domain);
        }

        if (! $this->robots->isAllowed($domain->robots_txt, $item->url)) {
            $item->update(['status' => 'failed', 'locked_by' => null]);
            $this->log($domain, null, $item->url, null, null, null, 'Blocked by robots.txt');

            return null;
        }

        return $domain;
    }

    protected function applyFetchResult(CrawlQueue $item, Domain $domain, array $result): bool
    {
        if ($result['error'] !== null || $result['status'] === null) {
            return $this->handleFailure($item, $domain, $result);
        }

        if ($result['status'] >= 400) {
            $this->log($domain, null, $item->url, $result['status'], $result['response_time_ms'], null, "HTTP {$result['status']}");

            return $this->handleFailure($item, $domain, $result);
        }

        if ($result['skipped_reason'] !== null) {
            $item->update(['status' => 'done', 'locked_by' => null]);
            $this->log($domain, null, $item->url, $result['status'], $result['response_time_ms'], null, 'Skipped: '.$result['skipped_reason']);

            return true;
        }

        $parsed = $this->parser->parse($result['body'] ?? '', $item->url);

        if (mb_strlen(trim($parsed['content_text'] ?? '')) < config('crawler.min_content_chars', 100)) {
            $item->update(['status' => 'done', 'locked_by' => null]);
            $this->log($domain, null, $item->url, $result['status'], $result['response_time_ms'], null, 'Skipped: content too short');

            return true;
        }

        $page = $this->savePage($domain, $item, $result, $parsed);

        $this->saveLinks($domain, $page, $item, $parsed['links']);

        $domain->increment('pages_count');
        $domain->update(['last_crawled_at' => now()]);

        $item->update(['status' => 'done', 'locked_by' => null]);

        $this->log($domain, $page, $item->url, $result['status'], $result['response_time_ms'], strlen($result['body'] ?? ''), null);

        $this->invalidateSearchCaches();

        return true;
    }

    protected function invalidateSearchCaches(): void
    {
        try {
            $redis = Cache::store('redis');
            $redis->tags(['search-results'])->flush();
            $redis->forget('search:domains');
            $redis->forget('admin:dashboard:stats');
            $redis->forget('admin:dashboard:pages_per_day');
        } catch (\Throwable $e) {
            // Cache store unreachable (e.g. Redis down in local dev); safe to ignore.
        }
    }

    protected function handleFailure(CrawlQueue $item, Domain $domain, array $result): bool
    {
        $status = $item->attempts >= $item->max_attempts ? 'failed' : 'pending';
        $item->update(['status' => $status, 'locked_by' => null]);

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
                'content_raw' => config('crawler.store_raw_html', false) ? $result['body'] : null,
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
        $discoveriesLeft = 5; // cap new domains per page so one link-heavy page can't stall a worker

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

                continue;
            }

            if ($link['is_external'] && $discoveriesLeft > 0 && config('crawler.auto_discover_domains', true)) {
                if ($this->maybeDiscoverDomain($link['url'])) {
                    $discoveriesLeft--;
                }
            }
        }
    }

    /**
     * Add a newly-seen external domain, up to the configured cap, and queue
     * its first page at low priority so seed domains keep getting crawled first.
     */
    protected function maybeDiscoverDomain(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return false;
        }

        if (Domain::where('name', $host)->exists()) {
            return false;
        }

        if (Domain::count() >= config('crawler.max_domains', 5000)) {
            return false;
        }

        try {
            $this->addDomain($url, priority: 0, fetchRobots: false);

            return true;
        } catch (\Throwable $e) {
            Log::warning("Failed to auto-discover domain from {$url}: ".$e->getMessage());

            return false;
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
