<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use App\Platforms\PlatformFactory;
use App\Platforms\PlatformTypeResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

readonly class ShowMetadata
{
    public function __construct(
        private PlatformTypeResolver $platformTypeResolver,
        private PlatformFactory $platformFactory,
    ) {}

    public function __invoke(Feed $feed, Request $request): View {
        $url = $request->str('url');

        try {
            // Detect the platform type (e.g. YouTube or SoundCloud) from the URL.
            $platformType = $this->platformTypeResolver->fromUrl($url);

            // Get the platform service by type.
            $platform = $this->platformFactory->make($platformType);

            // Parse the content id from the URL and get the metadata.
            $id = $platform->getIdFromUrl($url);
            $metadata = $platform->getMetadata($id);

            return view('components.addClipForm', [
                'feed' => $feed,
                'state' => 'metadata',
                'platformType' => $platformType,
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
