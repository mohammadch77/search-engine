<?php

return [
    // Number of parallel worker processes spawned by `crawl:mass` by default.
    'workers' => (int) env('CRAWLER_WORKERS', 15),

    // Used for new domains that don't specify a crawl-delay via robots.txt.
    'crawl_delay_ms' => (int) env('CRAWLER_CRAWL_DELAY_MS', 200),

    // Used for new domains.
    'max_depth' => (int) env('CRAWLER_MAX_DEPTH', 10),

    'timeout' => (int) env('CRAWLER_TIMEOUT', 10),
    'connect_timeout' => (int) env('CRAWLER_CONNECT_TIMEOUT', 5),

    // Number of URLs each worker fetches concurrently via Guzzle Pool.
    'fetch_concurrency' => (int) env('CRAWLER_FETCH_CONCURRENCY', 5),

    // Pages with less extracted text than this are skipped (not saved/parsed further).
    'min_content_chars' => (int) env('CRAWLER_MIN_CONTENT_CHARS', 100),

    // Pages larger than this (bytes) are skipped without being fully downloaded.
    'max_body_bytes' => (int) env('CRAWLER_MAX_BODY_BYTES', 2 * 1024 * 1024),

    // Content-Type prefixes that are downloaded and parsed; everything else is skipped.
    'allowed_content_types' => ['text/html', 'application/xhtml+xml'],

    // Store the raw HTML alongside extracted text. Disable to save disk space at scale.
    'store_raw_html' => filter_var(env('CRAWLER_STORE_RAW_HTML', false), FILTER_VALIDATE_BOOL),

    // When crawling finds links to domains we haven't seen, add them automatically
    // (robots.txt is still fetched and honored for each new domain).
    'auto_discover_domains' => filter_var(env('CRAWLER_AUTO_DISCOVER_DOMAINS', true), FILTER_VALIDATE_BOOL),

    // Hard cap on total domains, to keep organic growth bounded.
    'max_domains' => (int) env('CRAWLER_MAX_DOMAINS', 5000),
];
