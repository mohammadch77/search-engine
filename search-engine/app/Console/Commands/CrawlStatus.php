<?php

namespace App\Console\Commands;

use App\Models\CrawlQueue;
use App\Models\Domain;
use App\Models\Page;
use Illuminate\Console\Command;

class CrawlStatus extends Command
{
    protected $signature = 'crawl:status';

    protected $description = 'Show crawler statistics';

    public function handle(): int
    {
        $domainsCount = Domain::count();
        $pagesCount = Page::count();
        $indexedCount = Page::where('status', 'indexed')->count();

        $queuePending = CrawlQueue::where('status', 'pending')->count();
        $queueProcessing = CrawlQueue::where('status', 'processing')->count();
        $queueDone = CrawlQueue::where('status', 'done')->count();
        $queueFailed = CrawlQueue::where('status', 'failed')->count();

        $this->info('=== Crawler Status ===');
        $this->table(['Metric', 'Value'], [
            ['Domains', $domainsCount],
            ['Pages (total)', $pagesCount],
            ['Pages (indexed)', $indexedCount],
            ['Queue: pending', $queuePending],
            ['Queue: processing', $queueProcessing],
            ['Queue: done', $queueDone],
            ['Queue: failed', $queueFailed],
        ]);

        if ($domainsCount > 0) {
            $this->newLine();
            $this->info('=== Domains ===');
            $this->table(
                ['ID', 'Name', 'Status', 'Pages', 'Last Crawled'],
                Domain::all()->map(fn ($d) => [
                    $d->id,
                    $d->name,
                    $d->status,
                    $d->pages_count,
                    $d->last_crawled_at?->diffForHumans() ?? 'never',
                ])
            );
        }

        return self::SUCCESS;
    }
}
