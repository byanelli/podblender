<?php

namespace App\Platforms\Contracts;

use DateTimeInterface;

readonly class ClipMetadata
{
    public function __construct(
        public string $title,
        public string $description,
        public string $canonicalUrl,
        public DateTimeInterface $publishedAt,
        public SourceMetadata $source,
    ) {}
}
