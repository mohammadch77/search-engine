<?php

namespace App\Services;

class UrlNormalizer
{
    /**
     * Query parameters that carry no meaning for content identity (analytics/
     * tracking params). Stripped so ?utm_source=... variants of the same page
     * normalize to one URL.
     */
    protected const TRACKING_PARAMS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        'ref', 'fbclid', 'gclid', 'source', 'from',
    ];

    /**
     * File extensions that are never worth crawling as HTML pages.
     */
    protected const BLOCKED_EXTENSIONS = [
        'css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico', 'pdf',
        'zip', 'rar', 'mp3', 'mp4', 'avi', 'mov', 'wmv', 'doc', 'docx', 'xls',
        'xlsx', 'ppt', 'pptx', 'xml', 'json', 'rss', 'atom', 'woff', 'woff2',
        'ttf', 'eot', 'map',
    ];

    protected const BLOCKED_SCHEMES = ['mailto', 'tel', 'javascript', 'data'];

    protected const SESSION_PARAMS = ['sid', 'phpsessid', 'sessionid', 'jsessionid', 'session_id'];

    protected const MAX_URL_LENGTH = 2048;

    protected const MAX_QUERY_PARAMS = 3;

    /**
     * Normalize a URL for identity/hashing purposes: lowercase host, strip
     * tracking params and fragment, drop trailing slash, normalize encoding.
     */
    public function normalize(string $url): string
    {
        $parts = parse_url(trim($url));

        if ($parts === false || ! isset($parts['host'])) {
            return $url;
        }

        $scheme = strtolower($parts['scheme'] ?? 'http');
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $this->normalizePath($parts['path'] ?? '/');
        $query = $this->normalizeQuery($parts['query'] ?? '');

        return "{$scheme}://{$host}{$port}{$path}{$query}";
    }

    protected function normalizePath(string $path): string
    {
        // Normalize percent-encoding: decode then re-encode consistently so
        // %2Fabc and /abc-style variants converge.
        $decoded = rawurldecode($path);
        $segments = array_map('rawurlencode', explode('/', $decoded));
        $path = implode('/', $segments);

        if ($path === '') {
            $path = '/';
        }

        // Drop a single trailing slash, but keep the root "/" as-is.
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return $path === '' ? '/' : $path;
    }

    protected function normalizeQuery(string $query): string
    {
        if ($query === '') {
            return '';
        }

        parse_str($query, $params);

        foreach (array_keys($params) as $key) {
            if (in_array(strtolower($key), self::TRACKING_PARAMS, true)) {
                unset($params[$key]);
            }
        }

        if ($params === []) {
            return '';
        }

        ksort($params);

        return '?'.http_build_query($params);
    }

    /**
     * Whether a discovered link is worth queuing at all — filters out static
     * assets, non-http(s) schemes, fragment-only links, and abusive URLs.
     */
    public function shouldCrawl(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if (in_array($scheme, self::BLOCKED_SCHEMES, true)) {
            return false;
        }

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        if (strlen($url) > self::MAX_URL_LENGTH) {
            return false;
        }

        $path = $parts['path'] ?? '';

        if ($this->hasBlockedExtension($path)) {
            return false;
        }

        if (isset($parts['query'])) {
            parse_str($parts['query'], $queryParams);

            if (count($queryParams) > self::MAX_QUERY_PARAMS) {
                return false;
            }
        }

        if ($this->hasSessionId($url)) {
            return false;
        }

        return true;
    }

    public function hasBlockedExtension(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $extension !== '' && in_array($extension, self::BLOCKED_EXTENSIONS, true);
    }

    /**
     * Detect deep pagination traps like /page/6, /page/7, ... beyond a
     * reasonable depth (page 5) that rarely lead to unique, useful content.
     */
    public function isPaginationTrap(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        if (preg_match('#/page[/=](\d+)#i', $path, $m)) {
            return (int) $m[1] > 5;
        }

        $query = parse_url($url, PHP_URL_QUERY) ?? '';
        if ($query !== '') {
            parse_str($query, $params);
            foreach (['page', 'p', 'paged'] as $key) {
                if (isset($params[$key]) && is_numeric($params[$key]) && (int) $params[$key] > 5) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Detect calendar/date-archive traps like /2026/01/01, /2026/01/02, ...
     * which can generate effectively infinite unique URLs.
     */
    public function isCalendarTrap(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        return (bool) preg_match('#/(19|20)\d{2}/\d{1,2}/\d{1,2}(/|$)#', $path);
    }

    /**
     * Detect session-id query params that make otherwise-identical pages
     * look like infinite distinct URLs.
     */
    public function hasSessionId(string $url): bool
    {
        $query = parse_url($url, PHP_URL_QUERY) ?? '';

        if ($query === '') {
            return false;
        }

        parse_str($query, $params);

        foreach (array_keys($params) as $key) {
            if (in_array(strtolower($key), self::SESSION_PARAMS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True if the URL is any kind of crawl trap (pagination, calendar, or
     * session-id) that should be skipped even though shouldCrawl() allows it.
     */
    public function isTrap(string $url): bool
    {
        return $this->isPaginationTrap($url) || $this->isCalendarTrap($url) || $this->hasSessionId($url);
    }
}
