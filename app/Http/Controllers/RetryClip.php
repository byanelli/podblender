<?php

namespace App\Http\Controllers;

use App\Actions\QueueAudioClipDownload;
use App\Auth\Access\Gate;
use App\Enums\ClipProcessingState;
use App\Models\AudioClip;
use App\Models\Feed;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Response;

readonly class RetryClip
{
    /**
     * @throws AuthorizationException
     */
    public function __invoke(
        Gate $gate,
        QueueAudioClipDownload $queueDownload,
        Feed $feed,
        AudioClip $clip
    ): void {
        $gate->authorizeUpdate($feed);

        abort_unless(
            $feed->audioClips()->whereKey($clip)->exists(),
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        // Only a download that failed after exhausting its retries can be tried again. Unavailable is the platform
        // telling us the content is gone for good, so there is nothing to retry; Processing and Processed aren't
        // failures at all, and re-queueing either would either duplicate work in flight or throw away good audio.
        abort_unless(
            $clip->processing_state === ClipProcessingState::Failed,
            Response::HTTP_CONFLICT
        );

        // Back to Processing, so the feed page shows the clip as in flight again and the RSS keeps leaving it out
        // until the download succeeds.
        $clip->processing_state = ClipProcessingState::Processing;
        $clip->save();

        $queueDownload($clip);
    }
}
