<?php

namespace App\Http\Controllers;

use App\Auth\Access\Gate;
use App\Models\AudioClip;
use App\Models\Feed;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Response;

readonly class DeleteClip
{
    /**
     * @throws AuthorizationException
     */
    public function __invoke(
        Gate $gate,
        Feed $feed,
        AudioClip $clip
    ): void {
        $gate->authorizeUpdate($feed);

        abort_unless(
            $feed->audioClips()->whereKey($clip)->exists(),
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        $feed->audioClips()->detach($clip);
    }
}
