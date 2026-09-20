<?php

namespace App\Http\Controllers;

use App\Actions\FindOrCreateAudioClip;
use App\Auth\Access\Gate;
use App\Http\Requests\AudioClipUrlRequest;
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

        $platformType = $platforms->typeForUrl($request->url);

        $metadata = $platforms->for($platformType)->getClipMetadata($request->url);

        $clip = $findOrCreateAudioClip($platformType, $metadata);

        // A clip added by hand is dated now, so it appears at the top of the podcast app whatever its age on the
        // platform. syncWithoutDetaching makes a repeat add a no-op: the existing pivot row keeps its published_at and
        // the feed gets no duplicate episode.
        $feed->audioClips()->syncWithoutDetaching([
            $clip->id => ['published_at' => CarbonImmutable::now()],
        ]);
    }
}
