<?php

namespace App\Console\Commands;

use App\Services\CrawlManager;
use Illuminate\Console\Command;

class CrawlFetchWork extends Command
{
    protected $signature = 'crawl:fetch-work
        {--limit=0 : Stop after fetching this many items (0 = run until queue is empty or killed)}
        {--sleep=2 : Seconds to sleep between polls when the queue is empty}
        {--batch= : URLs fetched concurrently per iteration (defaults to config(crawler.fetch_concurrency))}
        {--id= : Worker identifier used for row locking/logging (defaults to the OS process id)}';

    protected $description = 'Pipeline 1 (internal): continuously fetch raw HTML into raw_pages, no parsing (one worker process)';

    protected bool $shouldStop = false;

    public function handle(CrawlManager $manager): int
    {
        $workerId = $this->option('id') ?: ('fetch-pid-'.getmypid());
        $limit = (int) $this->option('limit');
        $sleep = max(0, (int) $this->option('sleep'));
        $batchSize = max(1, (int) ($this->option('batch') ?: config('crawler.fetch_concurrency', 5)));

        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, fn () => $this->shouldStop = true);
            pcntl_signal(SIGTERM, fn () => $this->shouldStop = true);
        }

        $this->info("[{$workerId}] fetch worker started".($limit > 0 ? " (limit={$limit})" : ' (unlimited)'));

        $processed = 0;

        while (! $this->shouldStop) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $wanted = $limit > 0 ? min($batchSize, $limit - $processed) : $batchSize;
            $items = $manager->claimBatch($workerId, $wanted);

            if ($items === []) {
                sleep($sleep);

                continue;
            }

            try {
                $manager->fetchBatchOnly($items);
            } catch (\Throwable $e) {
                foreach ($items as $item) {
                    $item->update(['status' => 'failed', 'locked_by' => null]);
                }
                $this->error("[{$workerId}] batch failed: ".$e->getMessage());
            }

            $processed += count($items);
        }

        $this->info("[{$workerId}] fetch worker stopped after fetching {$processed} items");

        return self::SUCCESS;
    }
}
