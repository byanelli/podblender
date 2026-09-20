<?php

namespace App\Platforms\Feeds;

use Carbon\CarbonImmutable;

/**
 * One valid entry of a parsed RSS/Atom feed. FeedParser drops entries with no
 * link, title or publication date. The description and authors are optional.
 */
readonly class FeedItem
{
    /**
     * @param  array<int, string>  $authors
     */
    public function __construct(
        public string $url,
        public string $title,
        public CarbonImmutable $publishedAt,
        public ?string $description,
        public array $authors,
    ) {}
}
