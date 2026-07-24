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
        /**
         * The platform's conservative guess at how long a single download of
         * this clip takes, in seconds — pessimistic about bandwidth, pacing, and
         * narration rate, before any retries. The download job turns this into a
         * timeout by adding a buffer and multiplying by the expected attempts.
         */
        public ?int $estimatedDownloadTime = null,
    ) {}
}
