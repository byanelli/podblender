<?php

namespace App\Http\Controllers;

use App\Http\Urls;
use App\Models\Entry;
use App\Models\Feed;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;

class ShowFeedController
{
    public function __construct(private readonly Urls $urls) {}

    public function __invoke(Request $request, Feed $feed): Renderable {
        /** {@link resources/views/feed.blade.php} */
        return view('feed', [
            'urls' => $this->urls,
            'feed' => $feed->load(Feed::REL_AUDIO_CLIPS_FINISHED_PROCESSING)
        ]);
    }
}
