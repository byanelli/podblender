<?php

namespace App\Platforms\Contracts;

use App\Enums\AudioSourceKind;
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
         * What sort of thing this is on its platform. Defaults to a channel,
         * which is what every source was before playlists.
         */
        public AudioSourceKind $kind = AudioSourceKind::Channel,
        /**
         * Who publishes it, when that isn't its own name. A playlist is named
         * for its contents, so it carries the channel that owns it.
         */
        public ?string $authorName = null,
        /**
         * How many clips the source holds, when the platform can say cheaply.
         * Used to warn before committing to a large backfill.
         */
        public ?int $clipCount = null,
    ) {}
}
