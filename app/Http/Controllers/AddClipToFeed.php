<?php

namespace App\Http\Controllers;

use App\Actions\FindOrCreateAudioClip;
use App\Auth\Access\Gate;
use App\Http\Requests\AudioClipUrlRequest;
use App\Models\AudioClipFeed;
use App\Models\Feed;
use App\Platforms\Contracts\PlatformFactory;
use App\Platforms\Exceptions\MetadataException;
use App\Platforms\PlatformTypeResolver;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

readonly class AddClipToFeed
{
    /**
     * @throws AuthorizationException
     * @throws MetadataException
     */
    public function __invoke(
        Gate $gate,
        PlatformTypeResolver $platformTypeResolver,
        PlatformFactory $platformFactory,
        FindOrCreateAudioClip $findOrCreateAudioClip,
        AudioClipUrlRequest $request,
        Feed $feed,
    ): void {
        $gate->authorizeUpdate($feed);

        // Detect the platform type (e.g. YouTube or Web) from the URL.
        $platformType = $platformTypeResolver->fromUrl($request->url);

        $platform = $platformFactory->make($platformType);

        // Download the metadata from the platform.
        $metadata = $platform->getClipMetadata($request->url);

        // Find an existing audio clip in the database or create a new one from the metadata.
        $clip = $findOrCreateAudioClip($platformType, $metadata);

        // Attach the clip to the feed, presented as published now. Unlike a subscription, a clip added by hand is new
        // to this feed whenever it went up on the platform: someone adding a talk from three years ago wants it at the
        // top of their podcast app, not three years down the listing where they'll never see it.
        $feed->audioClips()->attach($clip, [
            AudioClipFeed::COL_PUBLISHED_AT => CarbonImmutable::now(),
        ]);
    }
}
