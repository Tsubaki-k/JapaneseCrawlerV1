<?php

namespace Hex\JapaneseCrawler\App\Helpers;

use Hex\JapaneseCrawler\App\Crawlers\KanjiCrawler;
use Hex\JapaneseCrawler\App\Interfaces\ICrawler;
use Hex\JapaneseCrawler\App\Interfaces\ICrawlerService;

class BaseCrawlerService implements ICrawlerService
{
    /**
     * Target Crawler.
     * @var string $crawler
     */
    protected string $crawler;

    /**
     * Make a new KanjiCrawlerService Instance.
     * @return self
     */
    public static function new(): self
    {
        return new self();
    }


    /**
     * Launch the target crawler.
     * @return void
     */
    public function crawl(): void
    {
        /**
         * @var ICrawler $crawler
         */
        $crawler = new $this->crawler();

        $crawler->start();
    }
}