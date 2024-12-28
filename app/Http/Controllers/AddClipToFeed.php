<?php

namespace App\Http\Controllers;

use App\Actions\FindOrCreateAudioClip;
use App\Auth\Access\Gate;
use App\Http\Requests\AudioClipUrlRequest;
use App\Models\Feed;
use App\Platforms\Exceptions\MetadataException;
use App\Platforms\Contracts\PlatformFactory;
use App\Platforms\PlatformTypeResolver;
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

        // Detect the platform type (e.g. YouTube or SoundCloud) from the URL.
        $platformType = $platformTypeResolver->fromUrl($request->getUrl());

        $platform = $platformFactory->make($platformType);

        // Download the metadata from the platform.
        $metadata = $platform->getClipMetadata($request->getUrl());

        // Find an existing audio clip in the database or create a new one from the metadata.
        $clip = $findOrCreateAudioClip($platformType, $metadata);

        // Attach the clip to the feed.
        $feed->audioClips()->attach($clip);
    }
}
