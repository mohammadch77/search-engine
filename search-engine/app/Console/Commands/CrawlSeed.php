<?php

namespace App\Console\Commands;

use App\Services\CrawlManager;
use Illuminate\Console\Command;

class CrawlSeed extends Command
{
    protected $signature = 'crawl:seed';

    protected $description = 'Add the default set of seed domains (Persian + English) to the crawl queue';

    protected const SEED_URLS = [
        'https://virgool.io',
        'https://zoomit.ir',
        'https://digikala.com/mag',
        'https://varzesh3.com',
        'https://fa.wikipedia.org',
        'https://en.wikipedia.org',
        'https://blogfa.com',
        'https://tebyan.net',
        'https://isna.ir',
        'https://farsnews.ir',
        'https://mehrnews.com',
        'https://tabnak.ir',
        'https://khabaronline.ir',
        'https://yjc.ir',
        'https://imdb.com',
        'https://medium.com',
        'https://stackoverflow.com',
        'https://github.com',
        'https://reddit.com',
        'https://mashable.com',
    ];

    public function handle(CrawlManager $manager): int
    {
        foreach (self::SEED_URLS as $url) {
            try {
                $domain = $manager->addDomain($url, priority: 10);
                $this->info("Added: {$domain->name}");
            } catch (\Throwable $e) {
                $this->warn("Skipped {$url}: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
