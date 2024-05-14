<?php

namespace App\Http\Controllers;

use App\Models\AudioClip;
use App\Models\Feed;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

class ShowFeed
{
    public function __invoke(Request $request, Feed $feed): View {
        /* @see resources/views/feed.blade.php */
        return view('feed', [
            'feed' => $feed->load([Feed::REL_AUDIO_CLIPS =>  function (Relation $query) {
                $query->orderByDesc(AudioClip::COL_CREATED_AT); // todo: this should be pivot created at
                $query->with(AudioClip::REL_AUDIO_SOURCE);
            }])
        ]);
    }
}
