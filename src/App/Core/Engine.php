<?php

namespace Hex\JapaneseCrawler\App\Core;

use Hex\JapaneseCrawler\App\Interfaces\ICrawlerService;

class Engine
{
    /**
     * @var string Crawler Service
     */
    private string $_service;



    /**
     * Launch the engine.
     * @return Engine
     */
    public static function new(): Engine
    {
        return new self();
    }



    /**
     * Set Crawler service to call
     * @param mixed $service
     * @return Engine
     */
    public function setService(mixed $service): Engine
    {
        $this->_service = $service;
        return $this;
    }

    /**
     * Start the crawler service.
     * @return $this
     */
    public function startService(): Engine
    {
        /**
         * @var ICrawlerService $service
         */
        $service = new $this->_service();

        //Come from ICrawlerService
        $service->crawl();

        return $this;
    }
}