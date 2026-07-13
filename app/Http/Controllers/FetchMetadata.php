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
    /**
     * @throws MetadataException
     */
    public function __invoke(
        PlatformTypeResolver $platformTypeResolver,
        PlatformFactory $platformFactory,
        AudioClipUrlRequest $request
    ): Responsable {
        $platformType = $platformTypeResolver->fromUrl($request->url);

        $platform = $platformFactory->make($platformType);

        $metadata = $platform->getClipMetadata($request->url);

        return new MetadataResponse(
            metadata: $metadata,
            platformType: $platformType,
        );
    }
}
