<?php

namespace App\Apis\Scraping;

/**
 * The fetched HTML, plus the final URL and HTTP status of the target page as
 * the scraping service reports them.
 */
readonly class ScrapeResult
{
    public function __construct(
        public string $content,
        public string $finalUrl,
        public int $statusCode,
    ) {}
}
