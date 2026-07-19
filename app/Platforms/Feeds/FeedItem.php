<?php

namespace App\Platforms\Feeds;

use Carbon\CarbonImmutable;

/**
 * One entry of a parsed RSS/Atom feed. Everything except the URL is optional:
 * real-world feeds omit dates, authors, even titles, and the Rss platform
 * decides what to do about the gaps (read the article page, forwarding what
 * the feed did say as hints).
 */
readonly class FeedItem
{
    /**
     * @param  array<int, string>  $authors
     */
    public function __construct(
        public string $url,
        public ?string $title,
        public ?string $description,
        public ?CarbonImmutable $publishedAt,
        public array $authors,
    ) {}
}
