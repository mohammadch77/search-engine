<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SupervisesWorkers;
use Illuminate\Console\Command;

class CrawlFetch extends Command
{
    use SupervisesWorkers;

    protected $signature = 'crawl:fetch
        {--workers= : Number of parallel fetch-only worker processes (defaults to config(crawler.fetch_workers))}
        {--limit=0 : Per-worker item limit (0 = run until the queue is empty or stopped)}';

    protected $description = 'Pipeline 1: launch fetch-only workers that download pages into raw_pages (no parsing)';

    protected bool $shouldStop = false;

    public function handle(): int
    {
        $workers = (int) ($this->option('workers') ?: config('crawler.fetch_workers', 15));
        $limit = (int) $this->option('limit');

        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, fn () => $this->shouldStop = true);
            pcntl_signal(SIGTERM, fn () => $this->shouldStop = true);
        }

        $this->info("Launching {$workers} crawl:fetch-work workers...");

        $extraArgs = ["--limit={$limit}"];
        $processes = $this->launchWorkers('crawl:fetch-work', $workers, 'fetch-worker', $extraArgs);

        while (! $this->shouldStop) {
            if (! $this->reapAndRestart($processes, 'crawl:fetch-work', 'fetch-worker', $extraArgs, $limit)) {
                break;
            }

            sleep(1);
        }

        $this->info('Stopping fetch workers...');
        $this->stopAll($processes);

        return self::SUCCESS;
    }
}
