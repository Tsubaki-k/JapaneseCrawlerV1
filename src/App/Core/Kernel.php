<?php

namespace Hex\JapaneseCrawler\App\Core;

use Hex\JapaneseCrawler\App\Services\KanjiCrawlerService;
use Hex\JapaneseCrawler\App\Services\SajedeCrawlerService;
use Hex\JapaneseCrawler\App\Services\WikiPediaCrawlerService;
use JetBrains\PhpStorm\NoReturn;

class Kernel
{
    /**
     * Make new instance of kernel.
     * @return Kernel
     */
    public static function new(): Kernel
    {
        return new self();
    }

    /**
     * Boot the application.
     * @return void
     */
    public function boot(): void
    {
        Engine
            ::new()
            ->setService(KanjiCrawlerService::class)
            ->startService();
    }
}