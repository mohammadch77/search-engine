<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class CrawlMass extends Command
{
    protected $signature = 'crawl:mass
        {--workers= : Number of parallel worker processes (defaults to config(crawler.workers))}
        {--limit=0 : Per-worker item limit (0 = run until the queue is empty or stopped)}';

    protected $description = 'Launch several crawl:work worker processes in parallel and supervise them';

    /** @var Process[] */
    protected array $processes = [];

    protected bool $shouldStop = false;

    public function handle(): int
    {
        $workers = (int) ($this->option('workers') ?: config('crawler.workers', 8));
        $limit = (int) $this->option('limit');

        $php = (new PhpExecutableFinder())->find() ?: 'php';
        $artisan = base_path('artisan');

        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, fn () => $this->shouldStop = true);
            pcntl_signal(SIGTERM, fn () => $this->shouldStop = true);
        }

        $this->info("Launching {$workers} crawl:work workers...");

        for ($i = 1; $i <= $workers; $i++) {
            $args = [$php, $artisan, 'crawl:work', "--id=worker-{$i}", "--limit={$limit}"];
            $process = new Process($args, base_path(), null, null, null);
            $process->start(function (string $type, string $buffer) use ($i) {
                foreach (explode("\n", trim($buffer)) as $line) {
                    if ($line !== '') {
                        $this->line("<comment>[worker-{$i}]</comment> {$line}");
                    }
                }
            });
            $this->processes[$i] = $process;
        }

        while (! $this->shouldStop) {
            $anyRunning = false;

            foreach ($this->processes as $i => $process) {
                if ($process->isRunning()) {
                    $anyRunning = true;
                    $process->checkTimeout();
                } elseif ($process->getExitCode() !== 0 && $limit === 0) {
                    // Restart a crashed worker so the whole batch keeps going unattended.
                    $this->warn("worker-{$i} exited (code {$process->getExitCode()}), restarting...");
                    $args = [$php, $artisan, 'crawl:work', "--id=worker-{$i}", "--limit={$limit}"];
                    $new = new Process($args, base_path(), null, null, null);
                    $new->start(function (string $type, string $buffer) use ($i) {
                        foreach (explode("\n", trim($buffer)) as $line) {
                            if ($line !== '') {
                                $this->line("<comment>[worker-{$i}]</comment> {$line}");
                            }
                        }
                    });
                    $this->processes[$i] = $new;
                    $anyRunning = true;
                }
            }

            if (! $anyRunning) {
                break;
            }

            sleep(1);
        }

        $this->info('Stopping workers...');
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                $process->stop(5);
            }
        }

        return self::SUCCESS;
    }
}
