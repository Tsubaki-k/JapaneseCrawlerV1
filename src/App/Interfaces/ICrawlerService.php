<?php

namespace Hex\JapaneseCrawler\App\Interfaces;

interface ICrawlerService
{
    public static function new();

    public function crawl();
}