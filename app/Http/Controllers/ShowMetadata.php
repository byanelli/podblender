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
            $platformType = $this->platformTypeResolver->fromUrl($url);

            $platform = $this->platformFactory->make($platformType);

            $metadata = $platform->getMetadata($url);

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
