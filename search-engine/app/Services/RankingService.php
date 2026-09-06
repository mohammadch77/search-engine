<?php

namespace App\Services;

use App\Models\Page;
use Illuminate\Support\Facades\DB;

class RankingService
{
    protected const WEIGHT_RELEVANCE = 0.60;

    protected const WEIGHT_FRESHNESS = 0.15;

    protected const WEIGHT_QUALITY = 0.15;

    protected const WEIGHT_POPULARITY = 0.10;

    protected const TITLE_MATCH_BONUS = 0.5;

    protected const META_MATCH_BONUS = 0.2;

    protected const IDEAL_WORD_COUNT = 1000;

    /**
     * Attach a normalized `score` to each page and sort the collection
     * by that score, descending. Pages keep their original `relevance`
     * (raw FULLTEXT score) attribute untouched.
     *
     * @param  \Illuminate\Support\Collection<int, Page>  $pages
     * @return \Illuminate\Support\Collection<int, Page>
     */
    public function rank(\Illuminate\Support\Collection $pages, string $query): \Illuminate\Support\Collection
    {
        if ($pages->isEmpty()) {
            return $pages;
        }

        $maxRelevance = (float) $pages->max('relevance') ?: 1.0;
        $maxWordCount = (float) $pages->max('word_count') ?: 1.0;
        $backlinkCounts = $this->backlinkCounts($pages->pluck('id')->all());
        $maxBacklinks = max(1, ...array_values($backlinkCounts ?: [0]));

        foreach ($pages as $page) {
            $page->setAttribute('score', $this->score(
                $page,
                $query,
                $maxRelevance,
                $maxWordCount,
                $backlinkCounts[$page->id] ?? 0,
                $maxBacklinks
            ));
        }

        return $pages->sortByDesc('score')->values();
    }

    protected function score(
        Page $page,
        string $query,
        float $maxRelevance,
        float $maxWordCount,
        int $backlinks,
        int $maxBacklinks
    ): float {
        $textRelevance = $this->textRelevanceScore($page, $query, $maxRelevance);
        $freshness = $this->freshnessScore($page);
        $quality = $this->qualityScore($page, $maxWordCount);
        $popularity = $this->popularityScore($backlinks, $maxBacklinks);

        return $textRelevance * self::WEIGHT_RELEVANCE
            + $freshness * self::WEIGHT_FRESHNESS
            + $quality * self::WEIGHT_QUALITY
            + $popularity * self::WEIGHT_POPULARITY;
    }

    protected function textRelevanceScore(Page $page, string $query, float $maxRelevance): float
    {
        $base = $maxRelevance > 0 ? ((float) $page->relevance) / $maxRelevance : 0.0;

        $terms = preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $titleMatch = false;
        $metaMatch = false;

        if ($terms !== []) {
            $title = mb_strtolower((string) $page->title);
            $meta = mb_strtolower((string) $page->meta_description);

            foreach ($terms as $term) {
                $needle = mb_strtolower($term);
                if ($needle === '') {
                    continue;
                }
                if (! $titleMatch && str_contains($title, $needle)) {
                    $titleMatch = true;
                }
                if (! $metaMatch && str_contains($meta, $needle)) {
                    $metaMatch = true;
                }
            }
        }

        $score = $base;
        if ($titleMatch) {
            $score += self::TITLE_MATCH_BONUS;
        }
        if ($metaMatch) {
            $score += self::META_MATCH_BONUS;
        }

        return min($score, 1.0 + self::TITLE_MATCH_BONUS + self::META_MATCH_BONUS);
    }

    protected function freshnessScore(Page $page): float
    {
        $crawledAt = $page->crawled_at;
        if (! $crawledAt) {
            return 0.0;
        }

        $daysSinceCrawl = max(0, $crawledAt->diffInDays(now()));

        return 1 / (1 + $daysSinceCrawl / 30);
    }

    protected function qualityScore(Page $page, float $maxWordCount): float
    {
        $wordCountScore = min((float) $page->word_count / self::IDEAL_WORD_COUNT, 1.0);

        $hasTitle = filled($page->title) ? 1.0 : 0.0;
        $hasMetaDescription = filled($page->meta_description) ? 1.0 : 0.0;

        return ($wordCountScore * 0.5) + ($hasTitle * 0.25) + ($hasMetaDescription * 0.25);
    }

    protected function popularityScore(int $backlinks, int $maxBacklinks): float
    {
        if ($maxBacklinks <= 0) {
            return 0.0;
        }

        return $backlinks / $maxBacklinks;
    }

    /**
     * Count internal backlinks (links.target_page_id) per page id.
     *
     * @param  array<int, int>  $pageIds
     * @return array<int, int>
     */
    protected function backlinkCounts(array $pageIds): array
    {
        if ($pageIds === []) {
            return [];
        }

        return DB::table('links')
            ->select('target_page_id', DB::raw('COUNT(*) as backlinks'))
            ->whereIn('target_page_id', $pageIds)
            ->groupBy('target_page_id')
            ->pluck('backlinks', 'target_page_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }
}
