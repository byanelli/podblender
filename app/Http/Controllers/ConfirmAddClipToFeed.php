<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use App\YoutubeDownloader\Client;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ConfirmAddClipToFeed {
    public function __construct(private readonly Client $youtube) {}

    public function __invoke(Request $request, Feed $feed): View {
        $url = $request->string('url');

        // todo: this controller should run in an AJAX request; the metadata shouldn't delay a request that returns HTML
        $metadata = $this->youtube->getMetadata($url);

        /* @see resources/views/showMetadata.blade.php */
        return view('showMetadata', ['url' => $url, 'feed' => $feed, 'metadata' => $metadata]);
    }
}
