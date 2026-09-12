<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrawlLog;
use App\Models\CrawlQueue;
use App\Models\Domain;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductPrice;
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
                'total_products' => Product::count(),
                'total_prices_tracked' => ProductPrice::count(),
                'products_per_domain' => $this->productsPerDomain(),
                'price_extraction_success_rate' => $this->priceExtractionSuccessRate(),
            ];
        });
    }

    /**
     * Best-effort: pages with has_product=true count as "successful
     * extractions"; pages with a http status (i.e. actually fetched and
     * parsed) count as "attempted", since there's no separate log of every
     * extraction attempt.
     */
    protected function priceExtractionSuccessRate(): float
    {
        $attempted = Page::whereNotNull('http_status')->count();
        $succeeded = Page::where('has_product', true)->count();

        return $attempted > 0 ? round($succeeded / $attempted, 4) : 0.0;
    }

    protected function productsPerDomain(): array
    {
        return ProductPrice::query()
            ->join('domains', 'domains.id', '=', 'product_prices.domain_id')
            ->select('domains.name as domain', DB::raw('COUNT(DISTINCT product_prices.product_id) as products_count'))
            ->groupBy('domains.name')
            ->orderByDesc('products_count')
            ->get()
            ->map(fn ($row) => ['domain' => $row->domain, 'products_count' => (int) $row->products_count])
            ->all();
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
