<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Pool;
use Psr\Http\Message\ResponseInterface;

class CrawlerService
{
    public const USER_AGENT = 'SearchEngineBot/1.0 (+https://example.com/bot)';

    /**
     * In-memory record of the last request time per domain host, used to
     * enforce crawl delay within a single process run.
     *
     * @var array<string, float>
     */
    protected static array $lastRequestAt = [];

    protected static ?Client $client = null;

    /**
     * A single, process-wide Guzzle client with a keep-alive curl handle so
     * repeated requests to the same host reuse the underlying TCP connection.
     */
    protected function client(): Client
    {
        return self::$client ??= new Client([
            'curl' => [
                CURLOPT_TCP_KEEPALIVE => 1,
                CURLOPT_TCP_KEEPIDLE => 120,
                CURLOPT_FORBID_REUSE => false,
                CURLOPT_FRESH_CONNECT => false,
            ],
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'text/html,application/xhtml+xml,*/*;q=0.8',
            ],
            'allow_redirects' => ['max' => 5],
        ]);
    }

    /**
     * Fetch a URL, checking the Content-Type before downloading the body and
     * aborting the download if it exceeds $maxBodyBytes.
     *
     * @return array{status: ?int, body: ?string, response_time_ms: ?int, content_type: ?string, error: ?string, skipped_reason: ?string}
     */
    public function fetch(string $url, int $crawlDelayMs = 200, ?int $maxBodyBytes = null): array
    {
        $maxBodyBytes ??= config('crawler.max_body_bytes', 2 * 1024 * 1024);

        $this->waitForCrawlDelay($url, $crawlDelayMs);

        $start = microtime(true);

        try {
            $response = $this->client()->request('GET', $url, [
                'stream' => true,
                'timeout' => config('crawler.timeout', 10),
                'connect_timeout' => config('crawler.connect_timeout', 5),
            ]);

            return $this->buildResult($response, $start, $maxBodyBytes);
        } catch (GuzzleException|\Throwable $e) {
            return [
                'status' => null,
                'body' => null,
                'response_time_ms' => (int) round((microtime(true) - $start) * 1000),
                'content_type' => null,
                'error' => $e->getMessage(),
                'skipped_reason' => null,
            ];
        } finally {
            self::$lastRequestAt[$this->hostFor($url)] = microtime(true);
        }
    }

    /**
     * Fetch several URLs concurrently (bounded by $concurrency) using a Guzzle
     * Pool. Crawl-delay is still enforced per host before each request starts.
     *
     * @param  array<string, int>  $urlDelayPairs  url => crawl delay in ms
     * @return array<string, array{status: ?int, body: ?string, response_time_ms: ?int, content_type: ?string, error: ?string, skipped_reason: ?string}>
     */
    public function fetchConcurrently(array $urlDelayPairs, ?int $concurrency = null, ?int $maxBodyBytes = null): array
    {
        $concurrency ??= config('crawler.fetch_concurrency', 5);
        $maxBodyBytes ??= config('crawler.max_body_bytes', 2 * 1024 * 1024);

        $results = [];
        $starts = [];
        $client = $this->client();

        $requests = function () use ($urlDelayPairs, $client, &$starts) {
            foreach ($urlDelayPairs as $url => $crawlDelayMs) {
                $this->waitForCrawlDelay($url, $crawlDelayMs);
                $starts[$url] = microtime(true);

                yield $url => fn () => $client->requestAsync('GET', $url, [
                    'stream' => true,
                    'timeout' => config('crawler.timeout', 10),
                    'connect_timeout' => config('crawler.connect_timeout', 5),
                ]);
            }
        };

        $pool = new Pool($client, $requests(), [
            'concurrency' => $concurrency,
            'fulfilled' => function (ResponseInterface $response, string $url) use (&$results, &$starts, $maxBodyBytes) {
                $results[$url] = $this->buildResult($response, $starts[$url], $maxBodyBytes);
                self::$lastRequestAt[$this->hostFor($url)] = microtime(true);
            },
            'rejected' => function ($reason, string $url) use (&$results, &$starts) {
                $results[$url] = [
                    'status' => null,
                    'body' => null,
                    'response_time_ms' => (int) round((microtime(true) - ($starts[$url] ?? microtime(true))) * 1000),
                    'content_type' => null,
                    'error' => $reason instanceof \Throwable ? $reason->getMessage() : (string) $reason,
                    'skipped_reason' => null,
                ];
                self::$lastRequestAt[$this->hostFor($url)] = microtime(true);
            },
        ]);

        $pool->promise()->wait();

        return $results;
    }

    /**
     * @return array{status: ?int, body: ?string, response_time_ms: ?int, content_type: ?string, error: ?string, skipped_reason: ?string}
     */
    protected function buildResult(ResponseInterface $response, float $start, int $maxBodyBytes): array
    {
        $responseTimeMs = (int) round((microtime(true) - $start) * 1000);
        $contentType = $response->getHeaderLine('Content-Type');

        if ($contentType !== '' && ! $this->isAllowedContentType($contentType)) {
            return [
                'status' => $response->getStatusCode(),
                'body' => null,
                'response_time_ms' => $responseTimeMs,
                'content_type' => $contentType,
                'error' => null,
                'skipped_reason' => 'non_html_content_type',
            ];
        }

        $declaredLength = (int) $response->getHeaderLine('Content-Length');
        if ($declaredLength > 0 && $declaredLength > $maxBodyBytes) {
            return [
                'status' => $response->getStatusCode(),
                'body' => null,
                'response_time_ms' => $responseTimeMs,
                'content_type' => $contentType,
                'error' => null,
                'skipped_reason' => 'too_large',
            ];
        }

        [$body, $tooLarge] = $this->readBodyLimited($response, $maxBodyBytes);

        if ($tooLarge) {
            return [
                'status' => $response->getStatusCode(),
                'body' => null,
                'response_time_ms' => $responseTimeMs,
                'content_type' => $contentType,
                'error' => null,
                'skipped_reason' => 'too_large',
            ];
        }

        return [
            'status' => $response->getStatusCode(),
            'body' => $body,
            'response_time_ms' => $responseTimeMs,
            'content_type' => $contentType ?: null,
            'error' => null,
            'skipped_reason' => null,
        ];
    }

    protected function isAllowedContentType(string $contentType): bool
    {
        foreach (config('crawler.allowed_content_types', ['text/html']) as $allowed) {
            if (str_contains($contentType, $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read the response body up to $maxBytes, aborting the connection if the
     * limit is exceeded (handles chunked responses with no Content-Length).
     *
     * @return array{0: ?string, 1: bool} [body, exceededLimit]
     */
    protected function readBodyLimited(ResponseInterface $response, int $maxBytes): array
    {
        $stream = $response->getBody();
        $buffer = '';

        while (! $stream->eof()) {
            $buffer .= $stream->read(8192);

            if (strlen($buffer) > $maxBytes) {
                $stream->close();

                return [null, true];
            }
        }

        $stream->close();

        return [$buffer, false];
    }

    protected function waitForCrawlDelay(string $url, int $crawlDelayMs): void
    {
        $host = $this->hostFor($url);
        $last = self::$lastRequestAt[$host] ?? null;

        if ($last === null || $crawlDelayMs <= 0) {
            return;
        }

        $elapsedMs = (microtime(true) - $last) * 1000;
        $remainingMs = $crawlDelayMs - $elapsedMs;

        if ($remainingMs > 0) {
            usleep((int) ($remainingMs * 1000));
        }
    }

    protected function hostFor(string $url): string
    {
        return parse_url($url, PHP_URL_HOST) ?: $url;
    }
}
