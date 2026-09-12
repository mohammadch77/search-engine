<?php

use App\Models\CrawlQueue;
use App\Models\Page;
use App\Models\SearchLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('crawl:mass')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::call(function () {
    CrawlQueue::where('status', 'processing')
        ->where('last_attempt_at', '<', now()->subMinutes(30))
        ->update(['status' => 'pending', 'locked_by' => null]);
})->daily()->name('crawl-queue-stale-cleanup');

Schedule::call(function () {
    SearchLog::where('searched_at', '<', now()->subDays(30))->delete();
})->daily()->name('search-logs-cleanup');

Schedule::call(function () {
    Page::query()
        ->where('has_product', true)
        ->where(function ($q) {
            $q->whereNull('crawled_at')->orWhere('crawled_at', '<', now()->subHours(24));
        })
        ->chunkById(500, function ($pages) {
            foreach ($pages as $page) {
                $existing = CrawlQueue::where('url_hash', $page->url_hash)->first();

                if ($existing) {
                    $existing->update(['status' => 'pending', 'priority' => 15, 'locked_by' => null]);

                    continue;
                }

                CrawlQueue::query()->insertOrIgnore([[
                    'domain_id' => $page->domain_id,
                    'url' => $page->url,
                    'url_hash' => $page->url_hash,
                    'priority' => 15,
                    'depth' => $page->depth,
                    'status' => 'pending',
                    'attempts' => 0,
                    'max_attempts' => 3,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]]);
            }
        });
})->daily()->name('product-pages-recrawl');
