<?php

namespace App\Http\Controllers;

use App\Actions\SaveYoutubeVideo;
use App\Http\Routes\Web;
use App\Jobs\DownloadAndStoreYoutubeVideo;
use App\Models\AudioClip;
use App\Models\Feed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AddClipToFeed
{
    public function __construct(private readonly SaveYoutubeVideo $saveYoutubeVideo) {}

    public function __invoke(Request $request, Feed $feed): RedirectResponse {
        $clipPlatformId = $request->post('id');

        // Find an existing audio clip in the database or get metadata from the platform and save the clip.
        /** @var AudioClip $clip */
        $clip = AudioClip::query()
            ->where(AudioClip::COL_PLATFORM_ID, $clipPlatformId)
            ->firstOr(fn() => $this->saveYoutubeVideo->__invoke($clipPlatformId));

        // If we're creating the clip in this request, queue a job to download it.
        if ($clip->wasRecentlyCreated) {
            dispatch(new DownloadAndStoreYoutubeVideo($clip))->onQueue('default');
        }

        // Attach the clip to the feed.
        $feed->audioClips()->attach($clip);

        // Redirect to the feed view.
        return redirect(Web::showFeed($feed));
    }
}
