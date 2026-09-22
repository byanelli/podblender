<?php

namespace App\Articles;

use Carbon\CarbonImmutable;

/**
 * The fields of a page's schema.org Article node. Every field is null or empty
 * when the page has no Article node or the node lacks it. Strings are trimmed
 * and never empty.
 */
readonly class JsonLd
{
    /**
     * @param  list<string>  $authors  Names, or profile URLs for authors given without a name.
     */
    public function __construct(
        public ?string $headline = null,
        public ?string $articleBody = null,
        public ?string $publisherName = null,
        public ?CarbonImmutable $datePublished = null,
        public array $authors = [],
        public ?int $wordCount = null,
    ) {}
}
