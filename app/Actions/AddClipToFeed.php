<?php

namespace App\Actions;

use App\Models\AudioClip;
use App\Models\Feed;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\Platforms;
use Carbon\CarbonImmutable;

/**
 * Adds the clip at a URL to a custom feed. Used by the feed page's form and by inbound email.
 */
readonly class AddClipToFeed
{
    public function __construct(
        private Platforms $platforms,
        private FindOrCreateAudioClip $findOrCreateAudioClip,
    ) {}

    /**
     * @throws PlatformException
     */
    public function __invoke(Feed $feed, string $url): AudioClip
    {
        $platformType = $this->platforms->typeForUrl($url);

        $metadata = $this->platforms->for($platformType)->getClipMetadata($url);

        $clip = ($this->findOrCreateAudioClip)($platformType, $metadata);

        // A clip added by hand is dated now, so it appears at the top of the podcast app whatever its age on the
        // platform. syncWithoutDetaching makes a repeat add a no-op: the existing pivot row keeps its published_at and
        // the feed gets no duplicate episode.
        $feed->audioClips()->syncWithoutDetaching([
            $clip->id => ['published_at' => CarbonImmutable::now()],
        ]);

        return $clip;
    }
}
