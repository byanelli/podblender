<?php

namespace App\Apis\Scrapfly;

/**
 * The outcome of a single Scrapfly scrape: the fetched HTML plus the target's
 * own signals (final URL and HTTP status) that Scrapfly reports back.
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
