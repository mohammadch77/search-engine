<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;

class HtmlParser
{
    /**
     * Parse raw HTML into structured data.
     *
     * @return array{
     *     title: ?string,
     *     meta_description: ?string,
     *     meta_keywords: ?string,
     *     content_text: string,
     *     language: ?string,
     *     word_count: int,
     *     links: array<int, array{url: string, anchor_text: ?string, is_external: bool}>,
     * }
     */
    public function parse(string $html, string $baseUrl): array
    {
        $dom = $this->loadDocument($html);
        $xpath = new DOMXPath($dom);

        $title = $this->extractTitle($xpath);
        $metaDescription = $this->extractMeta($xpath, 'description');
        $metaKeywords = $this->extractMeta($xpath, 'keywords');
        $contentText = $this->extractText($dom, $xpath);
        $language = $this->detectLanguage($xpath, $contentText);
        $links = $this->extractLinks($xpath, $baseUrl);

        return [
            'title' => $title,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'content_text' => $contentText,
            'language' => $language,
            'word_count' => $this->countWords($contentText),
            'links' => $links,
        ];
    }

    protected function loadDocument(string $html): DOMDocument
    {
        $dom = new DOMDocument();

        libxml_use_internal_errors(true);
        // Force UTF-8 interpretation regardless of the document's declared
        // charset so Persian/Arabic and other non-Latin content survives.
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();

        return $dom;
    }

    protected function extractTitle(DOMXPath $xpath): ?string
    {
        $node = $xpath->query('//title')->item(0);
        $title = $node ? trim($node->textContent) : null;

        return $title !== '' ? $title : null;
    }

    protected function extractMeta(DOMXPath $xpath, string $name): ?string
    {
        $node = $xpath->query("//meta[translate(@name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')='{$name}']")->item(0);
        $content = $node ? trim($node->getAttribute('content')) : null;

        return $content !== '' ? $content : null;
    }

    protected function extractText(DOMDocument $dom, DOMXPath $xpath): string
    {
        foreach ($xpath->query('//script|//style|//noscript') as $node) {
            $node->parentNode?->removeChild($node);
        }

        $body = $xpath->query('//body')->item(0) ?? $dom->documentElement;

        if ($body === null) {
            return '';
        }

        $text = $body->textContent;
        $text = preg_replace('/[ \t\x0B\f\r]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n\s*\n+/u', "\n", $text) ?? $text;

        return trim($text);
    }

    protected function detectLanguage(DOMXPath $xpath, string $text): ?string
    {
        $htmlNode = $xpath->query('//html')->item(0);
        if ($htmlNode) {
            $lang = trim($htmlNode->getAttribute('lang'));
            if ($lang !== '') {
                return strtolower(substr($lang, 0, 2));
            }
        }

        if (preg_match('/\p{Arabic}/u', $text)) {
            return 'fa';
        }

        if (preg_match('/[a-zA-Z]/', $text)) {
            return 'en';
        }

        return null;
    }

    protected function countWords(string $text): int
    {
        $tokens = preg_split('/[\s]+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return $tokens ? count($tokens) : 0;
    }

    /**
     * @return array<int, array{url: string, anchor_text: ?string, is_external: bool}>
     */
    protected function extractLinks(DOMXPath $xpath, string $baseUrl): array
    {
        $baseHost = parse_url($baseUrl, PHP_URL_HOST);
        $links = [];
        $seen = [];

        foreach ($xpath->query('//a[@href]') as $node) {
            $href = trim($node->getAttribute('href'));

            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                continue;
            }

            $absolute = $this->resolveUrl($href, $baseUrl);

            if ($absolute === null || isset($seen[$absolute])) {
                continue;
            }

            $seen[$absolute] = true;

            $links[] = [
                'url' => $absolute,
                'anchor_text' => trim($node->textContent) ?: null,
                'is_external' => parse_url($absolute, PHP_URL_HOST) !== $baseHost,
            ];
        }

        return $links;
    }

    public function resolveUrl(string $href, string $baseUrl): ?string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $href)) {
            return $this->normalize($href);
        }

        $base = parse_url($baseUrl);
        if ($base === false || ! isset($base['scheme'], $base['host'])) {
            return null;
        }

        $scheme = $base['scheme'];
        $host = $base['host'];
        $port = isset($base['port']) ? ':'.$base['port'] : '';

        if (str_starts_with($href, '//')) {
            return $this->normalize($scheme.':'.$href);
        }

        if (str_starts_with($href, '/')) {
            return $this->normalize("{$scheme}://{$host}{$port}{$href}");
        }

        $basePath = $base['path'] ?? '/';
        $baseDir = str_ends_with($basePath, '/') ? $basePath : dirname($basePath).'/';

        return $this->normalize("{$scheme}://{$host}{$port}".$this->collapseDots($baseDir.$href));
    }

    protected function collapseDots(string $path): string
    {
        $segments = explode('/', $path);
        $result = [];

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '') {
                continue;
            }
            if ($segment === '..') {
                array_pop($result);

                continue;
            }
            $result[] = $segment;
        }

        return '/'.implode('/', $result);
    }

    protected function normalize(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        $fragment = '';
        unset($parts['fragment']);

        $scheme = $parts['scheme'] ?? 'http';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return strtolower($scheme).'://'.strtolower($host).$port.($path ?: '/').$query;
    }
}
