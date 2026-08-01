<?php

namespace App\Http\Controllers;

use App\Http\Requests\AudioClipUrlRequest;
use App\Http\Responses\SourceMetadataResponse;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\Platforms;
use Illuminate\Contracts\Support\Responsable;

/**
 * Look up what's at a subscription URL without subscribing to it, so someone can
 * be shown what they're about to take on — its name, whether it's a channel or a
 * playlist, and how many episodes it holds — before choosing how far back to
 * reach.
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
