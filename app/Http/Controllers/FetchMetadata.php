<?php

namespace App\Http\Controllers;

use App\Http\Requests\AudioClipUrlRequest;
use App\Http\Responses\MetadataResponse;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\Platforms;
use Illuminate\Contracts\Support\Responsable;

readonly class FetchMetadata
{
    /**
     * @throws PlatformException
     */
    public function __invoke(
        Platforms $platforms,
        AudioClipUrlRequest $request
    ): Responsable {
        $platformType = $platforms->typeForUrl($request->url);

        $metadata = $platforms->for($platformType)->getClipMetadata($request->url);

        return new MetadataResponse(
            metadata: $metadata,
            platformType: $platformType,
        );
    }
}
