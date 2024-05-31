<?php

namespace App\Http\Controllers;

use App\Models\AudioClip;
use App\Models\Feed;
use App\Platforms\PlatformFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

readonly class ShowRss
{
    public function __construct(private PlatformFactory $platformFactory) {}

    public function __invoke(Request $request, Feed $feed): View {
        /** @see resources/views/rss.blade.php */
        return view('rss', [
            'feed' => $feed->load(Feed::REL_AUDIO_CLIPS_FINISHED_PROCESSING.'.'.AudioClip::REL_AUDIO_SOURCE),
            'platformFactory' => $this->platformFactory,
        ]);
    }
}
