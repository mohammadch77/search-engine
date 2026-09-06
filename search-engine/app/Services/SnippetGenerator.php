<?php

namespace App\Services;

class SnippetGenerator
{
    protected const SNIPPET_LENGTH = 200;

    public function generate(string $content, string $query, int $length = self::SNIPPET_LENGTH): string
    {
        $content = trim(preg_replace('/\s+/u', ' ', $content) ?? $content);

        if ($content === '') {
            return '';
        }

        $keywords = $this->extractKeywords($query);

        if (empty($keywords)) {
            return htmlspecialchars($this->truncate($content, $length), ENT_QUOTES, 'UTF-8');
        }

        $bestPosition = $this->findBestPosition($content, $keywords);
        $snippet = $this->extractAround($content, $bestPosition, $length);
        $snippet = htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8');

        return $this->highlight($snippet, $keywords);
    }

    /**
     * @return array<int, string>
     */
    protected function extractKeywords(string $query): array
    {
        $query = preg_replace('/"([^"]*)"/u', '$1', $query) ?? $query;
        $words = preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY);

        $keywords = [];
        foreach ($words as $word) {
            $clean = preg_replace('/[+\-<>()~*"@]+/u', '', $word) ?? $word;
            if (mb_strlen($clean) > 0) {
                $keywords[] = $clean;
            }
        }

        return array_values(array_unique($keywords));
    }

    /**
     * @param  array<int, string>  $keywords
     */
    protected function findBestPosition(string $content, array $keywords): int
    {
        $lowerContent = mb_strtolower($content);
        $bestPos = null;

        foreach ($keywords as $keyword) {
            $pos = mb_stripos($lowerContent, mb_strtolower($keyword));
            if ($pos !== false && ($bestPos === null || $pos < $bestPos)) {
                $bestPos = $pos;
            }
        }

        return $bestPos ?? 0;
    }

    protected function extractAround(string $content, int $position, int $length): string
    {
        $half = (int) floor($length / 2);
        $start = max(0, $position - $half);
        $snippet = mb_substr($content, $start, $length);

        $prefix = $start > 0 ? '…' : '';
        $suffix = ($start + $length) < mb_strlen($content) ? '…' : '';

        return $prefix.trim($snippet).$suffix;
    }

    /**
     * @param  array<int, string>  $keywords
     */
    protected function highlight(string $snippet, array $keywords): string
    {
        if (empty($keywords)) {
            return $snippet;
        }

        $pattern = implode('|', array_map(fn ($k) => preg_quote($k, '/'), $keywords));

        return preg_replace_callback("/({$pattern})/iu", function ($matches) {
            return '<mark>'.$matches[1].'</mark>';
        }, $snippet) ?? $snippet;
    }

    protected function truncate(string $content, int $length): string
    {
        if (mb_strlen($content) <= $length) {
            return $content;
        }

        return trim(mb_substr($content, 0, $length)).'…';
    }
}
