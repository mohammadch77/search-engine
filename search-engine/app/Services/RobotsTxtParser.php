<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RobotsTxtParser
{
    public function __construct(
        protected string $userAgent = 'SearchEngineBot',
    ) {
    }

    /**
     * Fetch robots.txt content from a domain's base URL.
     * Returns null if it can't be fetched (treated as "allow all").
     */
    public function fetch(string $baseUrl): ?string
    {
        $robotsUrl = rtrim($baseUrl, '/').'/robots.txt';

        try {
            $response = Http::withHeaders(['User-Agent' => $this->userAgent])
                ->timeout(15)
                ->get($robotsUrl);

            if ($response->successful()) {
                return $response->body();
            }
        } catch (\Throwable) {
            // Unreachable robots.txt — treat as allow all.
        }

        return null;
    }

    /**
     * Parse robots.txt content into per-user-agent rule groups.
     *
     * @return array<string, array{allow: string[], disallow: string[], crawl_delay: ?float}>
     */
    public function parse(?string $content): array
    {
        $groups = [];

        if ($content === null || trim($content) === '') {
            return $groups;
        }

        $currentAgents = [];

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line));

            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                // A new User-agent line after rules were already given to the
                // previous group starts a fresh group.
                if (! empty($currentAgents) && $this->groupHasRules($groups, $currentAgents)) {
                    $currentAgents = [];
                }
                $currentAgents[] = $value;
                foreach ($currentAgents as $agent) {
                    $groups[$agent] ??= ['allow' => [], 'disallow' => [], 'crawl_delay' => null];
                }

                continue;
            }

            if (empty($currentAgents)) {
                continue;
            }

            foreach ($currentAgents as $agent) {
                match ($field) {
                    'disallow' => $value !== '' ? $groups[$agent]['disallow'][] = $value : null,
                    'allow' => $groups[$agent]['allow'][] = $value,
                    'crawl-delay' => $groups[$agent]['crawl_delay'] = (float) $value,
                    default => null,
                };
            }
        }

        return $groups;
    }

    protected function groupHasRules(array $groups, array $agents): bool
    {
        foreach ($agents as $agent) {
            if (! empty($groups[$agent]['allow']) || ! empty($groups[$agent]['disallow']) || $groups[$agent]['crawl_delay'] !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Select the most specific matching rule group for our user agent, falling back to '*'.
     */
    protected function selectGroup(array $groups): ?array
    {
        foreach ($groups as $agent => $rules) {
            if (strtolower($agent) !== '*' && Str::contains(strtolower($this->userAgent), strtolower($agent))) {
                return $rules;
            }
        }

        return $groups['*'] ?? null;
    }

    public function isAllowed(?string $robotsTxt, string $path): bool
    {
        $groups = $this->parse($robotsTxt);
        $rules = $this->selectGroup($groups);

        if ($rules === null) {
            return true;
        }

        $path = '/'.ltrim(parse_url($path, PHP_URL_PATH) ?: '/', '/');

        $bestMatch = null;
        $bestLength = -1;

        foreach ($rules['disallow'] as $pattern) {
            if ($pattern === '' ) {
                continue;
            }
            if ($this->pathMatches($path, $pattern) && strlen($pattern) > $bestLength) {
                $bestMatch = 'disallow';
                $bestLength = strlen($pattern);
            }
        }

        foreach ($rules['allow'] as $pattern) {
            if ($this->pathMatches($path, $pattern) && strlen($pattern) > $bestLength) {
                $bestMatch = 'allow';
                $bestLength = strlen($pattern);
            }
        }

        return $bestMatch !== 'disallow';
    }

    protected function pathMatches(string $path, string $pattern): bool
    {
        $regex = preg_quote($pattern, '#');
        $regex = str_replace(['\*', '\$'], ['.*', '$'], $regex);

        return (bool) preg_match('#^'.$regex.'#', $path);
    }

    public function getCrawlDelay(?string $robotsTxt): ?int
    {
        $groups = $this->parse($robotsTxt);
        $rules = $this->selectGroup($groups);

        if ($rules === null || $rules['crawl_delay'] === null) {
            return null;
        }

        return (int) round($rules['crawl_delay'] * 1000);
    }
}
