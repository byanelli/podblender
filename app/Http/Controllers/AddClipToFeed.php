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
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;

readonly class AddClipToFeed
{
    public function __construct(
        private Gate $gate,
        private PlatformTypeResolver $platformTypeResolver,
        private PlatformFactory $platformFactory,
        private FindOrCreateAudioClip $findOrCreateAudioClip,
        private ResponseFactory $responseFactory,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws MetadataException
     */
    public function __invoke(AudioClipUrlRequest $request, Feed $feed): Response
    {
        $this->gate->authorizeUpdate($feed);

        // Detect the platform type (e.g. YouTube or SoundCloud) from the URL.
        $platformType = $this->platformTypeResolver->fromUrl($request->getUrl());

        $platform = $this->platformFactory->make($platformType);

        // Download the metadata from the platform.
        $metadata = $platform->getClipMetadata($request->getUrl());

        // Find an existing audio clip in the database or create a new one from the metadata.
        $clip = $this->findOrCreateAudioClip->__invoke($platformType, $metadata);

        // Attach the clip to the feed.
        $feed->audioClips()->attach($clip);

        return $this->responseFactory->make(status: Response::HTTP_OK);
    }
}
