<?php

namespace App\Apis\Scrapfly;

/**
 * The result of one Scrapfly scrape: the fetched HTML, plus the final URL and
 * HTTP status of the target page as Scrapfly reports them.
 */
readonly class ScrapflyResult
{
    public function __construct(
        public string $content,
        public string $finalUrl,
        public int $statusCode,
        public bool $success,
    ) {}
}
