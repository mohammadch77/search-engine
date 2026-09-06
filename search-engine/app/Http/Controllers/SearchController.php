<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        ]);

        $result = $this->searchService->search(
            $validated['q'],
            [
                'domain' => $validated['domain'] ?? null,
                'lang' => $validated['lang'] ?? null,
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
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
}
