<?php

namespace App\Http\Controllers;

use App\Models\AudioClip;
use App\Models\Feed;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ShowFeed
{
    public function __invoke(Request $request, Feed $feed): View {
        return view('feed', [
//            'feed' => $feed->load(Feed::REL_AUDIO_CLIPS.'.'.AudioClip::REL_AUDIO_SOURCE)
            'feed' => $feed->load([Feed::REL_AUDIO_CLIPS => function ($query) {
                $query->orderBy('created_at', 'desc');
                $query->with(AudioClip::REL_AUDIO_SOURCE);
            }])
        ]);
    }
}
