<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ShowRss
{
    public function __invoke(Request $request, Feed $feed): View {
        /** {@link resources/views/rss.blade.php} */
        return view('rss', [
            'feed' => $feed->load(Feed::REL_AUDIO_CLIPS_FINISHED_PROCESSING)
        ]);
    }
}
