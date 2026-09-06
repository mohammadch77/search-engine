<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SearchLog;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SearchLogController extends Controller
{
    public function index(): Response
    {
        $recent = SearchLog::query()
            ->orderByDesc('searched_at')
            ->limit(50)
            ->get(['query', 'results_count', 'response_time_ms', 'ip_address', 'searched_at']);

        $popular = SearchLog::query()
            ->select('query', DB::raw('COUNT(*) as count'))
            ->where('searched_at', '>=', now()->subDays(30))
            ->groupBy('query')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return Inertia::render('Admin/Searches', [
            'recent' => $recent,
            'popular' => $popular,
        ]);
    }
}
