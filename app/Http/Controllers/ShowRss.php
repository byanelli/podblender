<?php

namespace App\Http\Controllers;

use App\Http\Views;
use App\Models\Feed;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

readonly class ShowRss
{
    public function __invoke(
        Views $views,
        Request $request,
        Feed $feed
    ): Response {
        $feed->load('user', 'audioClipsFinishedProcessing');

        return $views->rss($feed);
    }
}
