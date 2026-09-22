<?php

namespace App\Articles;

use Carbon\CarbonImmutable;

/**
 * The OpenGraph, Twitter and article <meta> tags the Extractor uses. A field is
 * null when the page lacks the tag or its content is blank. Strings are
 * trimmed and HTML-decoded.
 */
readonly class MetaTags
{
    public function __construct(
        public ?string $ogTitle = null,
        public ?string $twitterTitle = null,
        public ?string $ogSiteName = null,
        public ?string $author = null,
        public ?string $articleAuthor = null,
        public ?CarbonImmutable $articlePublishedTime = null,
        public ?CarbonImmutable $ogPublishedTime = null,
    ) {}
}
