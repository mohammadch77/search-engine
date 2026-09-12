<?php

namespace App\Services;

use App\Models\Product;

/**
 * Resolves an extracted product name to an existing Product row, or creates
 * a new one. MySQL FULLTEXT gives us candidate rows; since true fuzzy scoring
 * isn't available in plain SQL, a token-overlap ratio decides whether a
 * candidate is "the same product" (pragmatic stand-in for real similarity).
 */
class ProductMatcher
{
    protected const SIMILARITY_THRESHOLD = 0.8;

    public function match(string $name, ?string $category = null, ?string $brand = null, ?string $imageUrl = null): Product
    {
        $normalized = $this->normalize($name);

        $best = $this->findBestCandidate($normalized);

        if ($best) {
            return $best;
        }

        return Product::create([
            'name' => mb_substr($name, 0, 500),
            'name_normalized' => mb_substr($normalized, 0, 500),
            'category' => $category,
            'brand' => $brand,
            'image_url' => $imageUrl,
        ]);
    }

    protected function findBestCandidate(string $normalized): ?Product
    {
        $booleanQuery = implode(' ', array_map(
            fn ($word) => '+'.$word.'*',
            array_filter(explode(' ', $normalized))
        ));

        if ($booleanQuery === '') {
            return null;
        }

        $candidates = Product::search($booleanQuery)->limit(20)->get();

        $bestProduct = null;
        $bestScore = 0.0;

        foreach ($candidates as $candidate) {
            $score = $this->similarity($normalized, $candidate->name_normalized);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestProduct = $candidate;
            }
        }

        return $bestScore > self::SIMILARITY_THRESHOLD ? $bestProduct : null;
    }

    /**
     * Simple token-overlap ratio (Jaccard-like): shared tokens / tokens in
     * the shorter set. Good enough as a pragmatic proxy for "is this the
     * same product listing" without a real fuzzy-matching library.
     */
    protected function similarity(string $a, string $b): float
    {
        $tokensA = array_unique(array_filter(explode(' ', $a)));
        $tokensB = array_unique(array_filter(explode(' ', $b)));

        if ($tokensA === [] || $tokensB === []) {
            return 0.0;
        }

        $shared = count(array_intersect($tokensA, $tokensB));
        $shorter = min(count($tokensA), count($tokensB));

        return $shorter > 0 ? $shared / $shorter : 0.0;
    }

    public function normalize(string $name): string
    {
        $name = trim($name);
        $name = mb_strtolower($name, 'UTF-8');

        $map = [
            "\u{064A}" => "\u{06CC}", // ي -> ی
            "\u{0643}" => "\u{06A9}", // ك -> ک
            "\u{0629}" => "\u{0647}", // ة -> ه
            "\u{200C}" => ' ',
        ];
        $name = strtr($name, $map);

        $name = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $name) ?? $name;
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return trim($name);
    }
}
