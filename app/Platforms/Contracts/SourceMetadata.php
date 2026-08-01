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
         * Who publishes the source. For anything that is its own author — a
         * channel, a website, an RSS feed — this repeats the name, which is
         * worth the redundancy: whoever asks who published something gets an
         * answer without having to know what type of source it came from, and
         * nothing downstream has to keep a rule about which types author
         * themselves. A playlist is named for what it collects, so it names the
         * channel that owns it instead.
         */
        public string $authorName,
        /**
         * What type of source this is on its platform. Defaults to a channel.
         */
        public AudioSourceType $type = AudioSourceType::Channel,
        /**
         * How many clips are in the source, when we can fetch this info easily
         * from the platform. Used to warn before committing to a large backfill.
         */
        public ?int $clipCount = null,
    ) {}
}
