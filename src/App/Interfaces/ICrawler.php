<?php

namespace Hex\JapaneseCrawler\App\Interfaces;

interface ICrawler
{
    /**
     * Start crawling.
     * @return void
     */
    public function start(): void;

    /**
     * Crawl concept and logic.
     * @return void
     */
    public function crawl(): void;
}