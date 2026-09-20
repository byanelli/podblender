<?php

namespace App\Apis\Scrapfly\Contracts;

use App\Apis\Scrapfly\ScrapflyException;
use App\Apis\Scrapfly\ScrapflyResult;

interface Client
{
    /**
     * Fetch a URL through Scrapfly's Anti-Scraping-Protection (ASP), which
     * passes Cloudflare/CAPTCHA checks that a plain HTTP client can't.
     *
     * This SPENDS SCRAPFLY CREDITS. Connection failures are retried, and no
     * thrown exception contains the API key.
     *
     * @throws ScrapflyException on a Scrapfly-level failure
     *                           or an exhausted connection retry
     */
    public function scrape(string $url, bool $renderJs = false): ScrapflyResult;
}
