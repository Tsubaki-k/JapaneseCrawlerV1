<?php

namespace Hex\JapaneseCrawler\App\Services;

use Hex\JapaneseCrawler\App\Crawlers\KanjiCrawler;
use Hex\JapaneseCrawler\App\Helpers\BaseCrawlerService;

class KanjiCrawlerService extends BaseCrawlerService
{
    protected string $crawler = KanjiCrawler::class;
}