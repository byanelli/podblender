<?php

namespace App\Platforms\Feeds;

use Carbon\CarbonImmutable;

/**
 * One valid entry of a parsed RSS/Atom feed. Validity is enforced by the
 * parser: an entry without a link, a title, and a publication date never
 * becomes a FeedItem — a feed that won't say what an item is called or when
 * it was published hasn't described an item at all. Only the description and
 * authors are genuinely optional.
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
