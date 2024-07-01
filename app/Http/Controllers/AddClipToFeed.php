<?php

namespace App\Http\Controllers;

use App\Actions\FindOrCreateAudioClip;
use App\Auth\Access\Gate;
use App\Http\Requests\AudioClipUrlRequest;
use App\Jobs\DownloadAndStoreAudioClip;
use App\Models\Feed;
use App\Platforms\Exceptions\MetadataException;
use App\Platforms\PlatformTypeResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;

readonly class AddClipToFeed
{
    public function __construct(
        private Gate                  $gate,
        private Dispatcher            $dispatcher,
        private PlatformTypeResolver  $platformTypeResolver,
        private FindOrCreateAudioClip $findOrCreateAudioClip,
        private ResponseFactory       $responseFactory,
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

        // Find an existing audio clip in the database or get metadata from the platform and save the clip.
        $clip = $this->findOrCreateAudioClip->__invoke($platformType, $request->getUrl());

        // If we're creating the clip in this request, queue a job to download it.
        if ($clip->wasRecentlyCreated) {
            $this->dispatcher->dispatch(new DownloadAndStoreAudioClip($clip));
        }

        // Attach the clip to the feed.
        $feed->audioClips()->attach($clip);

        return $this->responseFactory->make(status: Response::HTTP_OK);
    }
}
