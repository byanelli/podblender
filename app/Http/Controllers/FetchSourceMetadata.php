<?php

namespace App\Http\Controllers;

use App\Http\Requests\AudioClipUrlRequest;
use App\Http\Responses\SourceMetadataResponse;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\Platforms;
use Illuminate\Contracts\Support\Responsable;

/**
 * Look up a subscription URL without subscribing to it. The form shows the
 * source's name, type, and episode count before the user chooses how far back
 * to backfill.
 */
readonly class FetchSourceMetadata
{
    /**
     * @throws PlatformException
     */
    public function __invoke(
        Platforms $platforms,
        AudioClipUrlRequest $request,
    ): Responsable {
        $platformType = $platforms->subscribableTypeForUrl($request->url);

        return new SourceMetadataResponse(
            metadata: $platforms->for($platformType)->getSourceMetadata($request->url),
            platformType: $platformType,
        );
    }
}
