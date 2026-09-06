<?php

namespace App\Console\Commands;

use App\Models\CrawlQueue;
use App\Models\Page;
use App\Services\UrlNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrawlCleanup extends Command
{
    protected $signature = 'crawl:cleanup {--dry-run : Preview changes without deleting or updating anything}';

    protected $description = 'Clean up duplicate/junk pages and re-normalize crawl data';

    protected bool $dryRun = false;

    public function handle(UrlNormalizer $normalizer): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        if ($this->dryRun) {
            $this->warn('Running in --dry-run mode: no changes will be made.');
        }

        $stats = [
            'duplicate_pages_removed' => 0,
            'junk_pages_removed' => 0,
            'pages_renormalized' => 0,
            'pages_renormalize_duplicates_removed' => 0,
            'queue_renormalized' => 0,
            'queue_renormalize_duplicates_removed' => 0,
            'queue_junk_removed' => 0,
            'queue_duplicates_removed' => 0,
        ];

        $this->removeDuplicateContentPages($stats);
        $this->removeJunkPages($normalizer, $stats);
        $this->renormalizePages($normalizer, $stats);
        $this->renormalizeQueue($normalizer, $stats);
        $this->cleanQueue($normalizer, $stats);

        $this->newLine();
        $this->info($this->dryRun ? 'Dry-run results (nothing was changed):' : 'Cleanup complete:');
        $this->table(['Metric', 'Count'], collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all());

        return self::SUCCESS;
    }

    /**
     * Part 6: pages sharing a content_hash are the same content under
     * different URLs — keep the oldest, remove the rest.
     */
    protected function removeDuplicateContentPages(array &$stats): void
    {
        $this->line('Scanning for duplicate content...');

        $duplicateHashes = Page::query()
            ->whereNotNull('content_hash')
            ->groupBy('content_hash')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('content_hash');

        foreach ($duplicateHashes as $hash) {
            $ids = Page::where('content_hash', $hash)->orderBy('created_at')->pluck('id');
            $toDelete = $ids->slice(1);

            $stats['duplicate_pages_removed'] += $toDelete->count();

            if (! $this->dryRun && $toDelete->isNotEmpty()) {
                Page::whereIn('id', $toDelete)->delete();
            }
        }
    }

    /**
     * Part 6: pages that are actually CSS/JS/image/etc. files rather than
     * HTML documents (e.g. crawled before link filtering existed).
     */
    protected function removeJunkPages(UrlNormalizer $normalizer, array &$stats): void
    {
        $this->line('Scanning for junk (non-HTML) pages...');

        Page::query()->orderBy('id')->chunkById(500, function ($pages) use ($normalizer, &$stats) {
            $junkIds = [];

            foreach ($pages as $page) {
                $path = parse_url($page->url, PHP_URL_PATH) ?? '';
                $isJunkExtension = $normalizer->hasBlockedExtension($path);
                $isJunkContentType = $page->content_type
                    && ! str_contains($page->content_type, 'text/html')
                    && ! str_contains($page->content_type, 'xhtml');

                if ($isJunkExtension || $isJunkContentType) {
                    $junkIds[] = $page->id;
                }
            }

            $stats['junk_pages_removed'] += count($junkIds);

            if (! $this->dryRun && $junkIds !== []) {
                Page::whereIn('id', $junkIds)->delete();
            }
        });
    }

    /**
     * Part 6: recompute url_hash for every page using the current
     * normalization rules; pages that collapse onto an existing normalized
     * URL are duplicates and get removed.
     */
    protected function renormalizePages(UrlNormalizer $normalizer, array &$stats): void
    {
        $this->line('Re-normalizing page URLs...');

        Page::query()->orderBy('id')->chunkById(500, function ($pages) use ($normalizer, &$stats) {
            foreach ($pages as $page) {
                $normalizedUrl = $normalizer->normalize($page->url);
                $newHash = hash('sha256', $normalizedUrl);

                if ($newHash === $page->url_hash) {
                    continue;
                }

                $collision = Page::where('url_hash', $newHash)->where('id', '!=', $page->id)->exists();

                if ($collision) {
                    $stats['pages_renormalize_duplicates_removed']++;

                    if (! $this->dryRun) {
                        $page->delete();
                    }

                    continue;
                }

                $stats['pages_renormalized']++;

                if (! $this->dryRun) {
                    $page->update(['url' => $normalizedUrl, 'url_hash' => $newHash]);
                }
            }
        });
    }

    /**
     * Part 6/7: same re-normalization pass over crawl_queue.
     */
    protected function renormalizeQueue(UrlNormalizer $normalizer, array &$stats): void
    {
        $this->line('Re-normalizing crawl_queue URLs...');

        CrawlQueue::query()->orderBy('id')->chunkById(500, function ($items) use ($normalizer, &$stats) {
            foreach ($items as $item) {
                $normalizedUrl = $normalizer->normalize($item->url);
                $newHash = hash('sha256', $normalizedUrl);

                if ($newHash === $item->url_hash) {
                    continue;
                }

                $collision = CrawlQueue::where('url_hash', $newHash)->where('id', '!=', $item->id)->exists();

                if ($collision) {
                    $stats['queue_renormalize_duplicates_removed']++;

                    if (! $this->dryRun) {
                        $item->delete();
                    }

                    continue;
                }

                $stats['queue_renormalized']++;

                if (! $this->dryRun) {
                    $item->update(['url' => $normalizedUrl, 'url_hash' => $newHash]);
                }
            }
        });
    }

    /**
     * Part 7: drop pending queue items that the current filter rules would
     * now reject (junk extensions, traps), and any leftover duplicate
     * url_hash rows (keep the first).
     */
    protected function cleanQueue(UrlNormalizer $normalizer, array &$stats): void
    {
        $this->line('Cleaning crawl_queue of junk/trap/duplicate entries...');

        CrawlQueue::query()->where('status', 'pending')->orderBy('id')->chunkById(500, function ($items) use ($normalizer, &$stats) {
            $junkIds = [];

            foreach ($items as $item) {
                if (! $normalizer->shouldCrawl($item->url) || $normalizer->isTrap($item->url)) {
                    $junkIds[] = $item->id;
                }
            }

            $stats['queue_junk_removed'] += count($junkIds);

            if (! $this->dryRun && $junkIds !== []) {
                CrawlQueue::whereIn('id', $junkIds)->delete();
            }
        });

        $duplicateHashes = CrawlQueue::query()
            ->groupBy('url_hash')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('url_hash');

        foreach ($duplicateHashes as $hash) {
            $ids = CrawlQueue::where('url_hash', $hash)->orderBy('id')->pluck('id');
            $toDelete = $ids->slice(1);

            $stats['queue_duplicates_removed'] += $toDelete->count();

            if (! $this->dryRun && $toDelete->isNotEmpty()) {
                CrawlQueue::whereIn('id', $toDelete)->delete();
            }
        }
    }
}
