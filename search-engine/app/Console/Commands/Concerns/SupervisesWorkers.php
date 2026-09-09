<?php

namespace App\Console\Commands\Concerns;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Shared process-supervision logic for commands that launch N parallel
 * `php artisan ...` worker processes, tag their output, and restart any that
 * crash. Used by crawl:fetch, crawl:process, and crawl:start.
 */
trait SupervisesWorkers
{
    /**
     * @return array<int, Process>
     */
    protected function launchWorkers(string $artisanCommand, int $count, string $idPrefix, array $extraArgs = []): array
    {
        $php = (new PhpExecutableFinder())->find() ?: 'php';
        $artisan = base_path('artisan');
        $processes = [];

        for ($i = 1; $i <= $count; $i++) {
            $id = "{$idPrefix}-{$i}";
            $args = array_merge([$php, $artisan, $artisanCommand, "--id={$id}"], $extraArgs);
            $process = new Process($args, base_path(), null, null, null);
            $process->start(function (string $type, string $buffer) use ($id) {
                $this->relayOutput($id, $buffer);
            });
            $processes[$i] = $process;
        }

        return $processes;
    }

    protected function relayOutput(string $label, string $buffer): void
    {
        foreach (explode("\n", trim($buffer)) as $line) {
            if ($line !== '') {
                $this->line("<comment>[{$label}]</comment> {$line}");
            }
        }
    }

    /**
     * Restart any process in $processes that has exited, unless $limit > 0
     * (finite-limit runs are allowed to finish and stop). Returns whether any
     * process is still running after the check.
     *
     * @param  array<int, Process>  &$processes
     */
    protected function reapAndRestart(array &$processes, string $artisanCommand, string $idPrefix, array $extraArgs, int $limit): bool
    {
        $php = (new PhpExecutableFinder())->find() ?: 'php';
        $artisan = base_path('artisan');
        $anyRunning = false;

        foreach ($processes as $i => $process) {
            if ($process->isRunning()) {
                $anyRunning = true;
                $process->checkTimeout();

                continue;
            }

            if ($process->getExitCode() !== 0 && $limit === 0) {
                $id = "{$idPrefix}-{$i}";
                $this->warn("{$id} exited (code {$process->getExitCode()}), restarting...");
                $args = array_merge([$php, $artisan, $artisanCommand, "--id={$id}"], $extraArgs);
                $new = new Process($args, base_path(), null, null, null);
                $new->start(function (string $type, string $buffer) use ($id) {
                    $this->relayOutput($id, $buffer);
                });
                $processes[$i] = $new;
                $anyRunning = true;
            }
        }

        return $anyRunning;
    }

    protected function stopAll(array $processes): void
    {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(5);
            }
        }
    }
}
