<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SupervisesWorkers;
use App\Models\CrawlQueue;
use App\Models\RawPage;
use Illuminate\Console\Command;

class CrawlStart extends Command
{
    use SupervisesWorkers;

    protected $signature = 'crawl:start
        {--fetch-workers= : Number of fetch-only workers (defaults to config(crawler.fetch_workers), typically 15 — IO-bound, more is better)}
        {--process-workers= : Number of processor workers (defaults to config(crawler.process_workers), typically 5 — CPU-bound, fewer is fine)}
        {--status-interval=5 : Seconds between combined status refreshes}';

    protected $description = 'Launch both crawler pipelines together: fetch-only workers (pipeline 1) + processor workers (pipeline 2)';

    protected bool $shouldStop = false;

    public function handle(): int
    {
        $fetchWorkers = (int) ($this->option('fetch-workers') ?: config('crawler.fetch_workers', 15));
        $processWorkers = (int) ($this->option('process-workers') ?: config('crawler.process_workers', 5));
        $interval = max(1, (int) $this->option('status-interval'));

        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, fn () => $this->shouldStop = true);
            pcntl_signal(SIGTERM, fn () => $this->shouldStop = true);
        }

        $this->info("Starting crawler: {$fetchWorkers} fetch workers (pipeline 1) + {$processWorkers} process workers (pipeline 2)...");

        $fetchProcesses = $this->launchWorkers('crawl:fetch-work', $fetchWorkers, 'fetch-worker', ['--limit=0']);
        $processProcesses = $this->launchWorkers('crawl:process-work', $processWorkers, 'process-worker', ['--limit=0']);

        $lastFetched = RawPage::count();
        $lastProcessed = RawPage::where('processed', true)->count();
        $lastAt = microtime(true);

        while (! $this->shouldStop) {
            $fetchRunning = $this->reapAndRestart($fetchProcesses, 'crawl:fetch-work', 'fetch-worker', ['--limit=0'], 0);
            $processRunning = $this->reapAndRestart($processProcesses, 'crawl:process-work', 'process-worker', ['--limit=0'], 0);

            if (! $fetchRunning && ! $processRunning) {
                break;
            }

            $now = microtime(true);
            if ($now - $lastAt >= $interval) {
                $fetched = RawPage::count();
                $processed = RawPage::where('processed', true)->count();
                $elapsedMin = max(($now - $lastAt) / 60, 1 / 60);

                $this->line(sprintf(
                    '<info>[%s]</info> fetch: %s pages/min | process: %s pages/min | queue pending: %s | raw unprocessed: %s',
                    now()->toTimeString(),
                    number_format(($fetched - $lastFetched) / $elapsedMin, 1),
                    number_format(($processed - $lastProcessed) / $elapsedMin, 1),
                    number_format(CrawlQueue::where('status', 'pending')->count()),
                    number_format($fetched - $processed)
                ));

                $lastFetched = $fetched;
                $lastProcessed = $processed;
                $lastAt = $now;
            }

            sleep(1);
        }

        $this->info('Stopping all workers...');
        $this->stopAll($fetchProcesses);
        $this->stopAll($processProcesses);

        return self::SUCCESS;
    }
}
