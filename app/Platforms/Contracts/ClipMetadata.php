<?php

namespace App\Platforms\Contracts;

use BYanelli\Roma\Response\IsArrayable;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
readonly class ClipMetadata implements Arrayable
{
    use IsArrayable;

    public function __construct(
        public string $title,
        public string $description,
        public string $canonicalUrl,
        public DateTimeInterface $publishedAt,
        public SourceMetadata $source,
    ) {}
}
