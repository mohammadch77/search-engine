<?php

namespace App\Providers;

use App\Models\CrawlQueue;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('crawl-per-domain', function ($job) {
            $domainId = CrawlQueue::find($job->crawlQueueId)?->domain_id ?? 0;

            return Limit::perMinute(30)->by("domain:{$domainId}");
        });

        RateLimiter::for('search-api', function ($request) {
            return Limit::perMinute(60)->by($request->ip())->response(function () {
                return response()->json([
                    'message' => 'Too many search requests. Please slow down and try again shortly.',
                ], 429);
            });
        });

        RateLimiter::for('suggest-api', function ($request) {
            return Limit::perMinute(30)->by($request->ip())->response(function () {
                return response()->json([
                    'message' => 'Too many suggestion requests. Please slow down and try again shortly.',
                ], 429);
            });
        });
    }
}
