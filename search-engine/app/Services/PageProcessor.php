<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\RawPage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pipeline 2: parses raw_pages fetched by pipeline 1 (crawl:fetch) into the
 * pages table, extracting links and enqueuing newly-discovered URLs. Kept
 * separate from CrawlManager's fetch path so CPU-bound parsing/indexing
 * never blocks IO-bound HTTP fetching.
 */
class PageProcessor
{
    public function __construct(
        protected HtmlParser $parser,
        protected CrawlManager $crawlManager,
    ) {
    }

    /**
     * Atomically claim up to $batchSize unprocessed raw_pages rows.
     *
     * @return RawPage[]
     */
    public function claimBatch(string $workerId, int $batchSize): array
    {
        return DB::transaction(function () use ($workerId, $batchSize) {
            $items = RawPage::unprocessed()->orderBy('id')->lock('for update skip locked')->limit($batchSize)->get();

            if ($items->isEmpty()) {
                return [];
            }

            RawPage::whereIn('id', $items->pluck('id'))->update(['locked_by' => $workerId]);

            return $items->all();
        });
    }

    /**
     * @param  RawPage[]  $items
     */
    public function processBatch(array $items): void
    {
        foreach ($items as $raw) {
            try {
                $this->process($raw);
            } catch (\Throwable $e) {
                $this->releaseAfterFailure($raw, $e);
            }
        }
    }

    public function process(RawPage $raw): bool
    {
        $domain = $raw->domain;

        if (! $domain) {
            $raw->update(['processed' => true, 'locked_by' => null]);

            return false;
        }

        $parsed = $this->parser->parse($raw->html_content ?? '', $raw->url);

        $outcome = $this->crawlManager->indexParsedPage(
            $domain, $raw->url, $raw->url_hash, $raw->depth,
            $raw->http_status, $raw->content_type, $raw->html_content, $parsed
        );

        $raw->update(['processed' => true, 'locked_by' => null]);

        return $outcome['page'] !== null;
    }

    protected function releaseAfterFailure(RawPage $raw, \Throwable $e): void
    {
        $attempts = $raw->attempts + 1;
        $maxAttempts = config('crawler.processor_max_attempts', 3);

        Log::warning("Failed processing raw_page {$raw->id} ({$raw->url}): ".$e->getMessage());

        // Give up after a few failed attempts so one malformed row can't
        // wedge the processor queue forever.
        $raw->update([
            'processed' => $attempts >= $maxAttempts,
            'locked_by' => null,
            'attempts' => $attempts,
        ]);
    }
}
