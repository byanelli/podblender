<?php

namespace App\Apis\Scraping\Contracts;

use App\Apis\Scraping\ScrapeResult;
use App\Apis\Scraping\ScraperException;

/**
 * A scraping service that fetches a page a plain HTTP client can't, such as
 * one behind a Cloudflare check. Requests cost money.
 */
interface Scraper
{
    /**
     * @param  bool  $renderJs  Run the page's JavaScript in a browser before returning its HTML.
     *
     * @throws ScraperException when the service reports a failure or can't be reached
     */
    public function scrape(string $url, bool $renderJs = false): ScrapeResult;
}
