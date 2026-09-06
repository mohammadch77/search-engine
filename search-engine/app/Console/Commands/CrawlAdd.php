<?php

namespace App\Console\Commands;

use App\Services\CrawlManager;
use App\Services\UrlNormalizer;
use Illuminate\Console\Command;

class CrawlAdd extends Command
{
    protected $signature = 'crawl:add {url : The URL to start crawling from}';

    protected $description = 'Add a domain and its first URL to the crawl queue';

    public function handle(CrawlManager $manager, UrlNormalizer $normalizer): int
    {
        $url = $this->argument('url');

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error("Invalid URL: {$url}");

            return self::FAILURE;
        }

        $url = $normalizer->normalize($url);

        $domain = $manager->addDomain($url);

        $this->info("Domain added: {$domain->name} (id: {$domain->id})");
        $this->line("Queued: {$url}");

        return self::SUCCESS;
    }
}
