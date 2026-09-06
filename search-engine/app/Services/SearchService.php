<?php

namespace App\Services;

use App\Models\Page;
use App\Models\SearchLog;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SearchService
{
    public function __construct(protected SnippetGenerator $snippetGenerator)
    {
    }

    /**
     * @param  array{domain?: ?string, lang?: ?string, from?: ?string, to?: ?string}  $filters
     */
    public function search(string $query, array $filters = [], int $page = 1, int $perPage = 10): array
    {
        $start = microtime(true);

        $normalized = $this->normalizePersian($query);
        $booleanQuery = $this->prepareQuery($normalized);

        $results = new LengthAwarePaginator([], 0, $perPage, $page);
        $rows = collect();

        if ($booleanQuery !== '') {
            $builder = Page::query()
                ->indexed()
                ->selectRaw('pages.*, MATCH(title, content_text) AGAINST (? IN BOOLEAN MODE) AS relevance', [$booleanQuery])
                ->whereRaw('MATCH(title, content_text) AGAINST (? IN BOOLEAN MODE)', [$booleanQuery]);

            if (! empty($filters['domain'])) {
                $builder->whereHas('domain', function ($q) use ($filters) {
                    $q->where('name', $filters['domain']);
                });
            }

            if (! empty($filters['lang'])) {
                $builder->where('language', $filters['lang']);
            }

            if (! empty($filters['from'])) {
                $builder->whereDate('crawled_at', '>=', $filters['from']);
            }

            if (! empty($filters['to'])) {
                $builder->whereDate('crawled_at', '<=', $filters['to']);
            }

            $builder->orderByDesc('relevance');

            $results = $builder->with('domain')->paginate($perPage, ['*'], 'page', $page);
            $rows = $results->getCollection();
        }

        $items = $rows->map(function (Page $page) use ($normalized) {
            return [
                'title' => $page->title ?: $page->url,
                'url' => $page->url,
                'snippet' => $this->snippetGenerator->generate($page->content_text ?? '', $normalized),
                'domain' => $page->domain?->name,
                'language' => $page->language,
                'crawled_at' => $page->crawled_at?->toIso8601String(),
                'relevance' => (float) $page->relevance,
            ];
        })->values();

        $timeTakenMs = (int) round((microtime(true) - $start) * 1000);

        $this->logSearch($query, $results->total(), $timeTakenMs);

        return [
            'results' => $items,
            'total' => $results->total(),
            'page' => $results->currentPage(),
            'per_page' => $results->perPage(),
            'last_page' => $results->lastPage(),
            'time_taken_ms' => $timeTakenMs,
        ];
    }

    public function suggest(string $query, int $limit = 5): array
    {
        if (trim($query) === '') {
            return [];
        }

        return SearchLog::query()
            ->where('query', 'like', $this->normalizePersian($query).'%')
            ->select('query')
            ->groupBy('query')
            ->orderByRaw('COUNT(*) DESC')
            ->limit($limit)
            ->pluck('query')
            ->all();
    }

    /**
     * Clean the raw query and build a MySQL Boolean Mode expression.
     * - Quoted phrases become exact-match phrase groups.
     * - Bare words are prefixed with + so all terms are required by default
     *   and suffixed with * for prefix matching.
     */
    public function prepareQuery(string $query): string
    {
        $query = trim($query);

        if ($query === '') {
            return '';
        }

        $terms = [];
        $remainder = preg_replace_callback('/"([^"]+)"/u', function ($matches) use (&$terms) {
            $phrase = trim($matches[1]);
            if ($phrase !== '') {
                $terms[] = '+"'.str_replace('"', '', $phrase).'"';
            }

            return ' ';
        }, $query) ?? $query;

        $words = preg_split('/\s+/u', trim($remainder), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($words as $word) {
            $clean = preg_replace('/[+\-<>()~*"@]+/u', '', $word) ?? $word;
            if ($clean === '') {
                continue;
            }
            $terms[] = '+'.$clean.'*';
        }

        return implode(' ', $terms);
    }

    /**
     * Normalize Persian/Arabic variant characters so searches match
     * regardless of which glyph variant the crawled text used.
     * Arabic Yeh (ي, U+064A) -> Persian Yeh (ی, U+06CC)
     * Arabic Kaf (ك, U+0643) -> Persian Keheh (ک, U+06A9)
     */
    public function normalizePersian(string $text): string
    {
        $map = [
            "\u{064A}" => "\u{06CC}", // ي -> ی
            "\u{0643}" => "\u{06A9}", // ك -> ک
            "\u{0629}" => "\u{0647}", // ة -> ه
            "\u{06C0}" => "\u{0647}", // ۀ -> ه
            "\u{0660}" => '0', "\u{0661}" => '1', "\u{0662}" => '2', "\u{0663}" => '3', "\u{0664}" => '4',
            "\u{0665}" => '5', "\u{0666}" => '6', "\u{0667}" => '7', "\u{0668}" => '8', "\u{0669}" => '9',
            "\u{06F0}" => '0', "\u{06F1}" => '1', "\u{06F2}" => '2', "\u{06F3}" => '3', "\u{06F4}" => '4',
            "\u{06F5}" => '5', "\u{06F6}" => '6', "\u{06F7}" => '7', "\u{06F8}" => '8', "\u{06F9}" => '9',
            "\u{200C}" => ' ', // zero-width non-joiner -> space
        ];

        return strtr($text, $map);
    }

    protected function logSearch(string $query, int $resultsCount, int $timeTakenMs): void
    {
        SearchLog::create([
            'query' => $query,
            'results_count' => $resultsCount,
            'response_time_ms' => $timeTakenMs,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'searched_at' => now(),
        ]);
    }
}
