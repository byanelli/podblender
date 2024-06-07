<?php

namespace App\Http\Controllers;

use App\Http\Views;
use App\Models\Feed;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

readonly class ShowRss
{
    public function __construct(private Views $views) {}

    public function __invoke(Request $request, Feed $feed): View {
        $feed->load(Feed::REL_USER, Feed::REL_AUDIO_CLIPS_FINISHED_PROCESSING);

        return $this->views->rss($feed);
    }
}
