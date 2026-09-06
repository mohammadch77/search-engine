<?php

use App\Models\CrawlQueue;
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
