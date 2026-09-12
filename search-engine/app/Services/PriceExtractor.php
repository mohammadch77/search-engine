<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;

/**
 * Extracts a product name/price from a crawled e-commerce page's raw HTML.
 * Tries progressively less reliable strategies and keeps the highest-confidence
 * successful result. Callers should discard results with confidence <= 0.6.
 */
class PriceExtractor
{
    protected const DOMAIN_SELECTORS = [
        'digikala.com' => ['price' => '[data-testid="price-final"]', 'name' => '.product-title'],
        'torob.com' => ['price' => '.price', 'name' => '.product-name'],
        'basalam.com' => ['price' => '.price-value', 'name' => '.product-title'],
        'emalls.ir' => ['price' => '.product-price', 'name' => '.product-name'],
        'mobit.ir' => ['price' => '.price', 'name' => 'h1.product-title'],
        'mobile.ir' => ['price' => '.price-box', 'name' => '.product-name'],
        'technolife.ir' => ['price' => '.product-price', 'name' => '.product-title'],
    ];

    protected const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    protected const ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    /**
     * @return array{product_name: string, price: int, currency: string, availability: string, image_url: ?string, confidence: float}|null
     */
    public function extract(string $html, ?string $url = null, ?string $domain = null): ?array
    {
        $domain ??= $url ? parse_url($url, PHP_URL_HOST) : null;
        $domain = $domain ? preg_replace('/^www\./', '', $domain) : null;

        $candidates = array_filter([
            $this->extractStructuredData($html),
            $domain ? $this->extractViaSelectors($html, $domain) : null,
            $this->extractViaRegex($html),
        ]);

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);
        $best = $candidates[0];

        return $best['confidence'] > 0.6 ? $best : null;
    }

    protected function loadDom(string $html): DOMDocument
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();

        return $dom;
    }

    protected function extractStructuredData(string $html): ?array
    {
        $dom = $this->loadDom($html);
        $xpath = new DOMXPath($dom);

        foreach ($xpath->query('//script[@type="application/ld+json"]') as $node) {
            $decoded = json_decode($node->textContent, true);
            if (! is_array($decoded)) {
                continue;
            }

            foreach ($this->flattenJsonLd($decoded) as $item) {
                $result = $this->fromJsonLdProduct($item);
                if ($result) {
                    return $result;
                }
            }
        }

        $ogPrice = $this->metaContent($xpath, 'og:price:amount') ?? $this->metaContent($xpath, 'product:price:amount');
        $ogCurrency = $this->metaContent($xpath, 'og:price:currency') ?? $this->metaContent($xpath, 'product:price:currency');
        $ogImage = $this->metaContent($xpath, 'og:image');
        $ogTitle = $this->metaContent($xpath, 'og:title');

        if ($ogPrice !== null) {
            $amount = (float) preg_replace('/[^\d.]/', '', $ogPrice);
            if ($amount > 0) {
                return [
                    'product_name' => $ogTitle ?? 'Unknown Product',
                    'price' => $this->toRials($amount, $ogCurrency),
                    'currency' => 'toman',
                    'availability' => 'unknown',
                    'image_url' => $ogImage,
                    'confidence' => 0.9,
                ];
            }
        }

        $priceNode = $xpath->query('//*[@itemprop="price"]')->item(0);
        if ($priceNode) {
            $raw = $priceNode->getAttribute('content') ?: $priceNode->textContent;
            $amount = $this->parseNumber($raw);
            if ($amount > 0) {
                $nameNode = $xpath->query('//*[@itemprop="name"]')->item(0);
                $currencyNode = $xpath->query('//*[@itemprop="priceCurrency"]')->item(0);
                $currency = $currencyNode ? ($currencyNode->getAttribute('content') ?: $currencyNode->textContent) : null;

                return [
                    'product_name' => $nameNode ? trim($nameNode->textContent) : 'Unknown Product',
                    'price' => $this->toRials($amount, $currency),
                    'currency' => 'toman',
                    'availability' => 'unknown',
                    'image_url' => null,
                    'confidence' => 0.92,
                ];
            }
        }

        return null;
    }

    protected function flattenJsonLd(array $decoded): array
    {
        if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
            return $decoded['@graph'];
        }

        return array_is_list($decoded) ? $decoded : [$decoded];
    }

    protected function fromJsonLdProduct(array $item): ?array
    {
        $type = $item['@type'] ?? null;
        $types = is_array($type) ? $type : [$type];

        if (! in_array('Product', $types, true)) {
            return null;
        }

        $offers = $item['offers'] ?? null;
        if (is_array($offers) && array_is_list($offers)) {
            $offers = $offers[0] ?? null;
        }

        if (! is_array($offers) || ! isset($offers['price'])) {
            return null;
        }

        $amount = $this->parseNumber((string) $offers['price']);
        if ($amount <= 0) {
            return null;
        }

        $currency = $offers['priceCurrency'] ?? null;
        $availabilityRaw = strtolower((string) ($offers['availability'] ?? ''));
        $availability = 'unknown';
        if (str_contains($availabilityRaw, 'instock')) {
            $availability = 'in_stock';
        } elseif (str_contains($availabilityRaw, 'outofstock')) {
            $availability = 'out_of_stock';
        }

        $image = $item['image'] ?? null;
        if (is_array($image)) {
            $image = $image[0] ?? null;
        }

        return [
            'product_name' => $item['name'] ?? 'Unknown Product',
            'price' => $this->toRials($amount, $currency),
            'currency' => 'toman',
            'availability' => $availability,
            'image_url' => $image,
            'confidence' => 0.95,
        ];
    }

    protected function metaContent(DOMXPath $xpath, string $property): ?string
    {
        $node = $xpath->query("//meta[@property='{$property}' or @name='{$property}']")->item(0);
        $content = $node ? trim($node->getAttribute('content')) : null;

        return $content !== '' ? $content : null;
    }

    protected function extractViaSelectors(string $html, string $domain): ?array
    {
        $config = self::DOMAIN_SELECTORS[$domain] ?? null;
        if (! $config) {
            return null;
        }

        $dom = $this->loadDom($html);
        $xpath = new DOMXPath($dom);

        $priceNode = $this->queryCss($xpath, $config['price']);
        if (! $priceNode) {
            return null;
        }

        $amount = $this->parseNumber($priceNode->textContent);
        if ($amount <= 0) {
            return null;
        }

        $nameNode = $this->queryCss($xpath, $config['name']);
        $isRial = str_contains($priceNode->textContent, 'ریال');

        return [
            'product_name' => $nameNode ? trim($nameNode->textContent) : 'Unknown Product',
            'price' => $isRial ? (int) round($amount) : (int) round($amount * 10),
            'currency' => 'toman',
            'availability' => 'unknown',
            'image_url' => null,
            'confidence' => 0.75,
        ];
    }

    /**
     * Minimal CSS-selector-to-XPath translator for the simple selectors used
     * above ([attr="value"], .class, tag.class combos) — no external DOM
     * library is present in composer.json, so this stays dependency-free.
     */
    protected function queryCss(DOMXPath $xpath, string $selector): ?\DOMNode
    {
        if (preg_match('/^\[([a-zA-Z0-9_-]+)="([^"]+)"\]$/', $selector, $m)) {
            return $xpath->query("//*[@{$m[1]}=\"{$m[2]}\"]")->item(0);
        }

        if (preg_match('/^([a-zA-Z0-9]*)\.([a-zA-Z0-9_-]+)$/', $selector, $m)) {
            $tag = $m[1] !== '' ? $m[1] : '*';
            $class = $m[2];

            return $xpath->query("//{$tag}[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]")->item(0);
        }

        return null;
    }

    protected function extractViaRegex(string $html): ?array
    {
        $text = strip_tags($html);
        $text = $this->normalizePersianText($text);

        $pattern = '/([\d,\.]+)\s*(میلیون\s*)?(تومان|ریال)/u';

        if (! preg_match($pattern, $text, $m)) {
            return null;
        }

        $number = (float) str_replace(',', '', $m[1]);
        $isMillion = trim($m[2] ?? '') !== '';
        $unit = $m[3];

        if ($isMillion) {
            $number *= 1_000_000;
        }

        $rials = $unit === 'تومان' ? $number * 10 : $number;

        if ($rials <= 0) {
            return null;
        }

        return [
            'product_name' => 'Unknown Product',
            'price' => (int) round($rials),
            'currency' => 'toman',
            'availability' => 'unknown',
            'image_url' => null,
            'confidence' => $isMillion ? 0.65 : 0.55,
        ];
    }

    protected function parseNumber(string $raw): float
    {
        $normalized = $this->normalizePersianText($raw);
        $normalized = preg_replace('/[^\d.]/', '', $normalized) ?? '';

        return $normalized === '' ? 0.0 : (float) $normalized;
    }

    public function normalizePersianText(string $text): string
    {
        $text = str_replace(self::PERSIAN_DIGITS, ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], $text);
        $text = str_replace(self::ARABIC_DIGITS, ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], $text);

        return $text;
    }

    protected function toRials(float $amount, ?string $currency): int
    {
        $currency = strtoupper((string) $currency);

        if ($currency === 'IRT' || $currency === 'TOMAN') {
            return (int) round($amount * 10);
        }

        // IRR (rial) or unknown/missing currency: assume the source already
        // reports Rials, which is the common default for Iranian JSON-LD.
        return (int) round($amount);
    }
}
