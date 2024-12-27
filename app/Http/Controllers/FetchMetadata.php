<?php

namespace App\Http\Controllers;

use App\Http\Requests\AudioClipUrlRequest;
use App\Http\Responses\MetadataResponse;
use App\Platforms\Contracts\PlatformFactory;
use App\Platforms\Exceptions\MetadataException;
use App\Platforms\PlatformTypeResolver;
use Illuminate\Contracts\Support\Responsable;

readonly class FetchMetadata
{
    public function __construct(
        private PlatformTypeResolver $platformTypeResolver,
        private PlatformFactory $platformFactory,
    ) {}

    /**
     * @throws MetadataException
     */
    public function __invoke(AudioClipUrlRequest $request): Responsable
    {
        $url = $request->getUrl();

        $platformType = $this->platformTypeResolver->fromUrl($url);

        $platform = $this->platformFactory->make($platformType);

        $metadata = $platform->getClipMetadata($url);

        return new MetadataResponse(
            metadata: $metadata,
            platformType: $platformType,
        );
    }
}
