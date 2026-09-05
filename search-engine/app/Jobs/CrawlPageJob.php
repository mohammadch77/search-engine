<?php

namespace App\Jobs;

use App\Models\CrawlQueue;
use App\Services\CrawlManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;

class CrawlPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public int $crawlQueueId,
    ) {
    }

    public function middleware(): array
    {
        return [new RateLimited('crawl-per-domain')];
    }

    public function handle(CrawlManager $manager): void
    {
        $item = CrawlQueue::find($this->crawlQueueId);

        if (! $item || $item->status === 'done') {
            return;
        }

        $manager->processQueueItem($item);
    }

    public function failed(\Throwable $exception): void
    {
        CrawlQueue::where('id', $this->crawlQueueId)->update(['status' => 'failed']);
    }
}
