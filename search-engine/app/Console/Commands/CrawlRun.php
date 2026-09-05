<?php

namespace App\Console\Commands;

use App\Models\CrawlQueue;
use App\Services\CrawlManager;
use Illuminate\Console\Command;

class CrawlRun extends Command
{
    protected $signature = 'crawl:run {--limit=100 : Maximum number of queue items to process}';

    protected $description = 'Process pending items from the crawl queue';

    public function handle(CrawlManager $manager): int
    {
        $limit = (int) $this->option('limit');

        $available = CrawlQueue::pending()->count();
        $toProcess = min($limit, $available);

        if ($toProcess === 0) {
            $this->info('Nothing pending in the crawl queue.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($toProcess);
        $bar->start();

        $succeeded = 0;
        $failed = 0;

        for ($i = 0; $i < $toProcess; $i++) {
            $item = CrawlQueue::pending()->nextByPriority()->first();

            if (! $item) {
                break;
            }

            if ($manager->processQueueItem($item)) {
                $succeeded++;
            } else {
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Processed: {$i}, Succeeded: {$succeeded}, Failed: {$failed}");

        return self::SUCCESS;
    }
}
