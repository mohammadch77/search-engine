<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SearchController extends Controller
{
    public function __construct(protected SearchService $searchService)
    {
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:500'],
            'page' => ['nullable', 'integer', 'min:1'],
            'domain' => ['nullable', 'string', 'max:255'],
            'lang' => ['nullable', 'string', 'max:10'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'sort' => ['nullable', 'string', 'in:relevance,date'],
        ]);

        $result = $this->searchService->search(
            $validated['q'],
            [
                'domain' => $validated['domain'] ?? null,
                'lang' => $validated['lang'] ?? null,
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
                'sort' => $validated['sort'] ?? 'relevance',
            ],
            (int) ($validated['page'] ?? 1)
        );

        return response()->json($result);
    }

    public function suggest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:500'],
        ]);

        return response()->json([
            'suggestions' => $this->searchService->suggest($validated['q']),
        ]);
    }

    public function domains(): JsonResponse
    {
        $fetch = fn () => Domain::query()
            ->where('pages_count', '>', 0)
            ->orderBy('name')
            ->pluck('name');

        try {
            $domains = Cache::store('redis')->remember('search:domains', 300, $fetch);
        } catch (\Throwable $e) {
            $domains = $fetch();
        }

        return response()->json([
            'domains' => $domains,
        ]);
    }
}
