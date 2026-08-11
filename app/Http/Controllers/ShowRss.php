<?php

namespace App\Http\Controllers;

use App\Http\Views;
use App\Models\Feed;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

readonly class ShowRss
{
    public function __invoke(
        Views $views,
        Request $request,
        Feed $feed
    ): View {
        $feed->load('user', 'audioClipsFinishedProcessing');

        return $views->rss($feed);
    }
}
