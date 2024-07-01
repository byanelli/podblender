<?php

namespace App\Platforms\Contracts;

readonly class SourceMetadata
{
    public function __construct(
        public string $name,
        public string $canonicalUrl,
    ) {}
}
