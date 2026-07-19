<?php

namespace App\Platforms\Feeds;

readonly class ParsedFeed
{
    /**
     * @param  array<int, FeedItem>  $items
     */
    public function __construct(
        public ?string $title,
        public array $items,
    ) {}
}
