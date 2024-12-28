<?php

namespace App\Http\Controllers;

use App\Auth\Access\Gate;
use App\Http\Views;
use App\Models\AudioClip;
use App\Models\Feed;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Inertia\Response;

readonly class ShowFeed
{
    /**
     * @throws AuthorizationException
     */
    public function __invoke(
        Gate $gate,
        Views $views,
        Request $request,
        Feed $feed
    ): Response {
        $gate->authorizeView($feed);

        $feed->load([Feed::REL_AUDIO_CLIPS => fn (Relation $q) => $q->orderByDesc(AudioClip::COL_CREATED_AT)]);

        return $views->feed($feed);
    }
}
