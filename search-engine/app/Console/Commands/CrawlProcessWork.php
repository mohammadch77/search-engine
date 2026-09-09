<?php

namespace App\Console\Commands;

use App\Models\RawPage;
use App\Services\PageProcessor;
use Illuminate\Console\Command;

class CrawlProcessWork extends Command
{
    protected $signature = 'crawl:process-work
        {--limit=0 : Stop after processing this many items (0 = run until raw_pages is drained or killed)}
        {--sleep=2 : Seconds to sleep between polls when there is nothing unprocessed}
        {--batch=10 : Raw pages parsed per iteration}
        {--id= : Worker identifier used for row locking/logging (defaults to the OS process id)}';

    protected $description = 'Pipeline 2 (internal): continuously parse raw_pages into pages/links (one worker process)';

    protected bool $shouldStop = false;

    public function handle(PageProcessor $processor): int
    {
        $workerId = $this->option('id') ?: ('process-pid-'.getmypid());
        $limit = (int) $this->option('limit');
        $sleep = max(0, (int) $this->option('sleep'));
        $batchSize = max(1, (int) $this->option('batch'));

        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, fn () => $this->shouldStop = true);
            pcntl_signal(SIGTERM, fn () => $this->shouldStop = true);
        }

        $this->info("[{$workerId}] process worker started".($limit > 0 ? " (limit={$limit})" : ' (unlimited)'));

        $processed = 0;

        while (! $this->shouldStop) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $wanted = $limit > 0 ? min($batchSize, $limit - $processed) : $batchSize;
            $items = $processor->claimBatch($workerId, $wanted);

            if ($items === []) {
                sleep($sleep);

                continue;
            }

            try {
                $processor->processBatch($items);
            } catch (\Throwable $e) {
                foreach ($items as $item) {
                    RawPage::whereKey($item->id)->update(['locked_by' => null]);
                }
                $this->error("[{$workerId}] batch failed: ".$e->getMessage());
            }

            $processed += count($items);
        }

        $this->info("[{$workerId}] process worker stopped after processing {$processed} items");

        return self::SUCCESS;
    }
}
