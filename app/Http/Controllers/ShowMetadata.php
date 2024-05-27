<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use App\Apis\YtDlp\Client;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShowMetadata
{
    public function __construct(private readonly Client $youtube) {}

    public function __invoke(Feed $feed, Request $request): View {
        $url = $request->str('url');

        try {
            $metadata = $this->youtube->getYoutubeMetadata($url);

            return view('components.addClipForm', [
                'feed' => $feed,
                'state' => 'metadata',
                'metadata' => $metadata,
                'url' => $url,
            ]);
        } catch (\Throwable $t) {
            return view('components.addClipForm', [
                'feed' => $feed,
                'state' => 'error',
                'error' => $t->getMessage(),
            ]);
        }
    }
}
