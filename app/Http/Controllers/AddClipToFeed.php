<?php

namespace App\Http\Controllers;

use App\Actions\FindOrCreateAudioClip;
use App\Auth\Access\Gate;
use App\Http\Requests\AudioClipUrlRequest;
use App\Models\AudioClipFeed;
use App\Models\Feed;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\Platforms;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

readonly class AddClipToFeed
{
    /**
     * @throws AuthorizationException
     * @throws PlatformException
     */
    public function __invoke(
        Gate $gate,
        Platforms $platforms,
        FindOrCreateAudioClip $findOrCreateAudioClip,
        AudioClipUrlRequest $request,
        Feed $feed,
    ): void {
        $gate->authorizeUpdate($feed);

        // Detect the platform type (e.g. YouTube or Web) from the URL.
        $platformType = $platforms->typeForUrl($request->url);

        // Download the metadata from the platform.
        $metadata = $platforms->for($platformType)->getClipMetadata($request->url);

        // Find an existing audio clip in the database or create a new one from the metadata.
        $clip = $findOrCreateAudioClip($platformType, $metadata);

        // Attach the clip to the feed, presented as published now. Unlike a subscription, a clip added by hand is new
        // to this feed whenever it went up on the platform: someone adding a talk from three years ago wants it at the
        // top of their podcast app, not three years down the listing where they'll never see it.
        //
        // syncWithoutDetaching rather than attach so that adding the same clip twice is idempotent: the second add
        // leaves the existing pivot row (and its published_at) alone instead of inserting a duplicate that would show
        // up as a second copy of the episode in the feed.
        $feed->audioClips()->syncWithoutDetaching([
            $clip->id => [AudioClipFeed::COL_PUBLISHED_AT => CarbonImmutable::now()],
        ]);
    }
}
