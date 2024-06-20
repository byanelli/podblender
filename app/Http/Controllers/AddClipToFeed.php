<?php

namespace App\Http\Controllers;

use App\Actions\CreateAudioClip;
use App\Auth\Access\Gate;
use App\Http\Requests\AudioClipUrlRequest;
use App\Jobs\DownloadAndStoreAudioClip;
use App\Models\AudioClip;
use App\Models\Feed;
use App\Platforms\Contracts\PlatformFactory;
use App\Platforms\PlatformTypeResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;

readonly class AddClipToFeed
{
    public function __construct(
        private Gate                 $gate,
        private Dispatcher           $dispatcher,
        private PlatformTypeResolver $platformTypeResolver,
        private PlatformFactory      $platformFactory,
        private CreateAudioClip      $createAudioClip,
        private ResponseFactory      $responseFactory,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(AudioClipUrlRequest $request, Feed $feed): Response {
        $request->validate(['url' => 'required|url:http,https']);

        $this->gate->authorizeUpdate($feed);

        $url = $request->getUrl();

        // Detect the platform type (e.g. YouTube or SoundCloud) from the URL.
        $platformType = $this->platformTypeResolver->fromUrl($url);

        // Get the platform service by type.
        $platform = $this->platformFactory->make($platformType);

        // A platform will typically have many URLs pointing to the same content. Here we get the URL in a canonical
        // form to avoid duplication.
        $url = $platform->getCanonicalUrl($url);

        // Find an existing audio clip in the database or get metadata from the platform and save the clip.
        /** @var AudioClip $clip */
        $clip = AudioClip::query()
            ->where(AudioClip::COL_PLATFORM_URL, $url)
            ->firstOr(fn() => $this->createAudioClip->__invoke($platformType, $url));

        // If we're creating the clip in this request, queue a job to download it.
        if ($clip->wasRecentlyCreated) {
            $this->dispatcher->dispatch(new DownloadAndStoreAudioClip($clip));
        }

        // Attach the clip to the feed.
        $feed->audioClips()->attach($clip);

        return $this->responseFactory->make(status: Response::HTTP_OK);
    }
}
