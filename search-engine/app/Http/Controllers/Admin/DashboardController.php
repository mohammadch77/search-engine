<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrawlLog;
use App\Models\CrawlQueue;
use App\Models\Domain;
use App\Models\Page;
use App\Models\SearchLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => $this->getStats(),
            'pagesPerDay' => $this->getPagesPerDay(),
            'searchesPerDay' => $this->getSearchesPerDay(),
        ]);
    }

    protected function remember(string $key, int $ttl, \Closure $callback): array
    {
        try {
            return Cache::store('redis')->remember($key, $ttl, $callback);
        } catch (\Throwable $e) {
            return $callback();
        }
    }

    protected function getStats(): array
    {
        return $this->remember('admin:dashboard:stats', 30, function () {
            $queueCounts = CrawlQueue::query()
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status');

            $lastHourPages = CrawlLog::query()
                ->where('crawled_at', '>=', now()->subHour())
                ->whereNotNull('page_id')
                ->count();

            return [
                'total_pages' => Page::count(),
                'total_domains' => Domain::count(),
                'queue' => [
                    'pending' => (int) ($queueCounts['pending'] ?? 0),
                    'processing' => (int) ($queueCounts['processing'] ?? 0),
                    'failed' => (int) ($queueCounts['failed'] ?? 0),
                    'done' => (int) ($queueCounts['done'] ?? 0),
                ],
                'searches_today' => SearchLog::whereDate('searched_at', today())->count(),
                'crawl_speed_per_hour' => $lastHourPages,
            ];
        });
    }

    protected function getPagesPerDay(): array
    {
        return $this->remember('admin:dashboard:pages_per_day', 30, function () {
            $rows = CrawlLog::query()
                ->selectRaw('DATE(crawled_at) as date, COUNT(*) as count')
                ->whereNotNull('page_id')
                ->where('crawled_at', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            return $rows->map(fn ($row) => ['date' => $row->date, 'count' => (int) $row->count])->all();
        });
    }

    protected function getSearchesPerDay(): array
    {
        return $this->remember('admin:dashboard:searches_per_day', 30, function () {
            $rows = SearchLog::query()
                ->selectRaw('DATE(searched_at) as date, COUNT(*) as count')
                ->where('searched_at', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            return $rows->map(fn ($row) => ['date' => $row->date, 'count' => (int) $row->count])->all();
        });
    }
}
