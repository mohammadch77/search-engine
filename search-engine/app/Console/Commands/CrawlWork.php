<?php

namespace App\Console\Commands;

use App\Services\CrawlManager;
use Illuminate\Console\Command;

class CrawlWork extends Command
{
    protected $signature = 'crawl:work
        {--limit=0 : Stop after processing this many items (0 = run until queue is empty or killed)}
        {--sleep=2 : Seconds to sleep between polls when the queue is empty}
        {--id= : Worker identifier used for row locking/logging (defaults to the OS process id)}';

    protected $description = 'Continuously claim and process crawl queue items (one worker process)';

    protected bool $shouldStop = false;

    public function handle(CrawlManager $manager): int
    {
        $workerId = $this->option('id') ?: ('pid-'.getmypid());
        $limit = (int) $this->option('limit');
        $sleep = max(0, (int) $this->option('sleep'));

        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, fn () => $this->shouldStop = true);
            pcntl_signal(SIGTERM, fn () => $this->shouldStop = true);
        }

        $this->info("[{$workerId}] worker started".($limit > 0 ? " (limit={$limit})" : ' (unlimited)'));

        $processed = 0;

        while (! $this->shouldStop) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $item = $manager->claimNext($workerId);

            if (! $item) {
                sleep($sleep);

                continue;
            }

            try {
                $manager->processQueueItem($item, alreadyClaimed: true);
            } catch (\Throwable $e) {
                $item->update(['status' => 'failed', 'locked_by' => null]);
                $this->error("[{$workerId}] {$item->url}: ".$e->getMessage());
            }

            $processed++;
        }

        $this->info("[{$workerId}] worker stopped after processing {$processed} items");

        return self::SUCCESS;
    }
}
