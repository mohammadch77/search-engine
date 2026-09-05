<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

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

    /**
     * Fetch a URL and return response details.
     *
     * @return array{status: ?int, body: ?string, headers: array, response_time_ms: ?int, content_type: ?string, error: ?string}
     */
    public function fetch(string $url, int $crawlDelayMs = 1000): array
    {
        $this->waitForCrawlDelay($url, $crawlDelayMs);

        $start = microtime(true);

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'text/html,application/xhtml+xml,*/*;q=0.8',
            ])
                ->timeout(30)
                ->connectTimeout(10)
                ->withOptions(['allow_redirects' => ['max' => 5]])
                ->get($url);

            $responseTimeMs = (int) round((microtime(true) - $start) * 1000);

            return [
                'status' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers(),
                'response_time_ms' => $responseTimeMs,
                'content_type' => $response->header('Content-Type'),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            $responseTimeMs = (int) round((microtime(true) - $start) * 1000);

            return [
                'status' => null,
                'body' => null,
                'headers' => [],
                'response_time_ms' => $responseTimeMs,
                'content_type' => null,
                'error' => $e->getMessage(),
            ];
        } finally {
            self::$lastRequestAt[$this->hostFor($url)] = microtime(true);
        }
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
