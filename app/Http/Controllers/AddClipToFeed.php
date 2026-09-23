<?php

namespace App\Http\Controllers;

use App\Actions\AddClipToFeed as AddClipToFeedAction;
use App\Auth\Access\Gate;
use App\Http\Requests\AudioClipUrlRequest;
use App\Models\Feed;
use App\Platforms\Exceptions\PlatformException;
use Illuminate\Auth\Access\AuthorizationException;

readonly class AddClipToFeed
{
    /**
     * @throws AuthorizationException
     * @throws PlatformException
     */
    public function __invoke(
        Gate $gate,
        AddClipToFeedAction $addClipToFeed,
        AudioClipUrlRequest $request,
        Feed $feed,
    ): void {
        $gate->authorizeUpdate($feed);

        $addClipToFeed($feed, $request->url);
    }
}
