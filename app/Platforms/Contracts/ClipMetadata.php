<?php

namespace App\Platforms\Contracts;

readonly class ClipMetadata
{
    public function __construct(
        public string $title,
        public string $description,
        public string $canonicalUrl,
        public SourceMetadata $source,
    ) {}
}
