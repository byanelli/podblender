<?php

namespace App\Apis\Scrapfly\Contracts;

use App\Apis\Scrapfly\ScrapflyException;
use App\Apis\Scrapfly\ScrapflyResult;

interface Client
{
    /**
     * Fetch a URL through Scrapfly's Anti-Scraping-Protection (ASP), which
     * clears Cloudflare/CAPTCHA walls that a raw HTTP client can't pass.
     *
     * This SPENDS SCRAPFLY CREDITS. Retries transient connection drops and
     * sanitizes the API key out of any thrown exception.
     *
     * @throws ScrapflyException on a Scrapfly-level failure
     *                           or an exhausted connection retry
     */
    public function scrape(string $url, bool $renderJs = false): ScrapflyResult;
}
