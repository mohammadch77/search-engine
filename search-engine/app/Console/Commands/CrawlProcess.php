<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SupervisesWorkers;
use Illuminate\Console\Command;

class CrawlProcess extends Command
{
    use SupervisesWorkers;

    protected $signature = 'crawl:process
        {--workers= : Number of parallel processor worker processes (defaults to config(crawler.process_workers))}
        {--limit=0 : Per-worker item limit (0 = run until raw_pages is drained or stopped)}';

    protected $description = 'Pipeline 2: launch processor workers that parse raw_pages into pages/links and enqueue new URLs';

    protected bool $shouldStop = false;

    public function handle(): int
    {
        $workers = (int) ($this->option('workers') ?: config('crawler.process_workers', 5));
        $limit = (int) $this->option('limit');

        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, fn () => $this->shouldStop = true);
            pcntl_signal(SIGTERM, fn () => $this->shouldStop = true);
        }

        $this->info("Launching {$workers} crawl:process-work workers...");

        $extraArgs = ["--limit={$limit}"];
        $processes = $this->launchWorkers('crawl:process-work', $workers, 'process-worker', $extraArgs);

        while (! $this->shouldStop) {
            if (! $this->reapAndRestart($processes, 'crawl:process-work', 'process-worker', $extraArgs, $limit)) {
                break;
            }

            sleep(1);
        }

        $this->info('Stopping processor workers...');
        $this->stopAll($processes);

        return self::SUCCESS;
    }
}
