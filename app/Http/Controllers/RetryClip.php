<?php

namespace App\Http\Controllers;

use App\Auth\Access\Gate;
use App\Enums\ClipProcessingState;
use App\Jobs\DownloadAndStoreAudioClip;
use App\Models\AudioClip;
use App\Models\Feed;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Response;

readonly class RetryClip
{
    /**
     * @throws AuthorizationException
     */
    public function __invoke(
        Gate $gate,
        Dispatcher $dispatcher,
        Feed $feed,
        AudioClip $clip
    ): void {
        $gate->authorizeUpdate($feed);

        abort_unless(
            $feed->audioClips()->whereKey($clip)->exists(),
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        // Only a Failed clip can be retried. Unavailable content is permanently gone, and re-queueing a Processing or
        // Processed clip would duplicate a running download or replace good audio.
        abort_unless(
            $clip->processing_state === ClipProcessingState::Failed,
            Response::HTTP_CONFLICT
        );

        // The feed page shows a Processing clip as in progress, and the RSS leaves it out.
        $clip->processing_state = ClipProcessingState::Processing;
        $clip->save();

        $dispatcher->dispatch(new DownloadAndStoreAudioClip($clip));
    }
}
