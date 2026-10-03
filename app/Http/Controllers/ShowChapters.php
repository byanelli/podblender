<?php

namespace App\Http\Controllers;

use App\Http\Responses\ChaptersResponse;
use App\Models\AudioClip;
use App\Models\Feed;
use Illuminate\Http\Response;

readonly class ShowChapters
{
    // Scoped to the feed, so clip ids can't be enumerated without a feed's UUID.
    public function __invoke(Feed $feed, AudioClip $audioClip): ChaptersResponse
    {
        abort_if($audioClip->chapters === [], Response::HTTP_NOT_FOUND);

        return ChaptersResponse::for($audioClip->chapters);
    }
}
