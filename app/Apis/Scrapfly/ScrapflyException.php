<?php

namespace App\Apis\Scrapfly;

use App\Apis\Scraping\ScraperException;

/**
 * A Scrapfly-level failure: the API returned an error, reported the scrape
 * unsuccessful, or the connection failed on every retry.
 */
class ScrapflyException extends ScraperException
{
    /**
     * Takes no $previous. The API key is in the request's query string, and
     * cURL and Guzzle exception messages include the full URL. For the same
     * reason, never build $message from one of those messages.
     */
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
