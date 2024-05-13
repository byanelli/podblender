<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use App\YoutubeDownloader\Client;
use Illuminate\Http\Request;

class ConfirmAddClipToFeed {
    public function __construct(private readonly Client $youtube) {}

    public function __invoke(Request $request, Feed $feed) {
        $url = $request->string('url');

        // todo: make this generic for different platforms
        $metadata = $this->youtube->getMetadata($url);

        return view('showMetadata', ['url' => $url, 'feed' => $feed, 'metadata' => $metadata]);
    }
}
