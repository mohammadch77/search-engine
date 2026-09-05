<?php

namespace App\Console\Commands;

use App\Models\CrawlQueue;
use App\Models\Domain;
use App\Models\Page;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrawlMonitor extends Command
{
    protected $signature = 'crawl:monitor {--interval=5 : Seconds between refreshes} {--target=1000000 : Page count used for the ETA estimate}';

    protected $description = 'Live crawler stats: pages/min, queue size, domains discovered, ETA, disk usage';

    protected bool $shouldStop = false;

    public function handle(): int
    {
        $interval = max(1, (int) $this->option('interval'));
        $target = (int) $this->option('target');

        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, fn () => $this->shouldStop = true);
        }

        $lastCount = Page::count();
        $lastAt = microtime(true);

        while (! $this->shouldStop) {
            $pagesTotal = Page::count();
            $now = microtime(true);
            $elapsedMin = max(($now - $lastAt) / 60, 1 / 60);
            $rate = ($pagesTotal - $lastCount) / $elapsedMin;

            $queuePending = CrawlQueue::where('status', 'pending')->count();
            $queueProcessing = CrawlQueue::where('status', 'processing')->count();
            $domains = Domain::count();
            $diskMb = $this->databaseSizeMb();

            $remaining = max($target - $pagesTotal, 0);
            $etaMinutes = $rate > 0 ? $remaining / $rate : null;

            $this->output->write("\033[2J\033[H"); // clear screen
            $this->info('=== Crawler Monitor ==='.'  ('.now()->toTimeString().')');
            $this->table(['Metric', 'Value'], [
                ['Pages crawled (total)', number_format($pagesTotal)],
                ['Pages / minute', number_format($rate, 1)],
                ['Queue: pending', number_format($queuePending)],
                ['Queue: processing', number_format($queueProcessing)],
                ['Domains discovered', number_format($domains)],
                ['Target', number_format($target)],
                ['ETA to target', $etaMinutes === null ? 'n/a' : $this->formatDuration($etaMinutes)],
                ['DB size (MB)', number_format($diskMb, 1)],
            ]);

            $lastCount = $pagesTotal;
            $lastAt = $now;

            sleep($interval);
        }

        $this->info('Monitor stopped.');

        return self::SUCCESS;
    }

    protected function databaseSizeMb(): float
    {
        try {
            $database = config('database.connections.mysql.database');

            $row = DB::selectOne(
                'SELECT SUM(data_length + index_length) AS bytes FROM information_schema.tables WHERE table_schema = ?',
                [$database]
            );

            return $row && $row->bytes ? round($row->bytes / (1024 * 1024), 1) : 0.0;
        } catch (\Throwable) {
            return 0.0;
        }
    }

    protected function formatDuration(float $minutes): string
    {
        if ($minutes < 60) {
            return round($minutes).'m';
        }

        $hours = floor($minutes / 60);
        $mins = round($minutes - $hours * 60);

        return "{$hours}h {$mins}m";
    }
}
