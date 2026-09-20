<?php

namespace App\Platforms\Contracts;

use App\Enums\AudioSourceType;
use BYanelli\Roma\Response\IsArrayable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
readonly class SourceMetadata implements Arrayable
{
    use IsArrayable;

    public function __construct(
        public string $name,
        public string $canonicalUrl,
        /**
         * The source's publisher. For a channel, a website or an RSS feed this
         * repeats the name, so callers don't need to check the source type. For
         * a playlist it is the playlist's channel.
         */
        public string $authorName,
        /**
         * The type of source on its platform.
         */
        public AudioSourceType $type = AudioSourceType::Channel,
        /**
         * The number of clips in the source, when the platform reports it.
         * Used to warn before a large backfill.
         */
        public ?int $clipCount = null,
    ) {}
}
