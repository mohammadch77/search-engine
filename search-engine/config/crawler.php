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

    // Hard cap on queued+crawled URLs per domain, so one large site can't
    // fill the entire queue and starve other domains.
    'max_urls_per_domain' => (int) env('CRAWLER_MAX_URLS_PER_DOMAIN', 10000),

    // Max URLs from the same domain claimed into one concurrent fetch batch.
    'max_concurrent_per_domain' => (int) env('CRAWLER_MAX_CONCURRENT_PER_DOMAIN', 2),

    // Minimum crawl-log samples for a domain before its error rate is judged.
    'error_rate_sample_size' => (int) env('CRAWLER_ERROR_RATE_SAMPLE_SIZE', 20),

    // Auto-pause a domain once its recent error rate exceeds this fraction.
    'error_rate_pause_threshold' => (float) env('CRAWLER_ERROR_RATE_PAUSE_THRESHOLD', 0.5),

    // Pipeline 2 (crawl:process): give up on a raw_pages row after this many
    // failed parse attempts instead of retrying it forever.
    'processor_max_attempts' => (int) env('CRAWLER_PROCESSOR_MAX_ATTEMPTS', 3),

    // Default worker counts for the two-pipeline crawler (crawl:start).
    'fetch_workers' => (int) env('CRAWLER_FETCH_WORKERS', 15),
    'process_workers' => (int) env('CRAWLER_PROCESS_WORKERS', 5),

    // When true, only .ir domains and the known Iranian e-commerce domain
    // list are enqueued/crawled (see CrawlManager::IRANIAN_ECOMMERCE_DOMAINS).
    'iran_only' => filter_var(env('CRAWLER_IRAN_ONLY', false), FILTER_VALIDATE_BOOL),
];
