<?php

namespace App\Platforms;

readonly class Metadata
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public string $sourceId,
        public string $sourceName,
    ) {}
}
