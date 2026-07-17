<?php

namespace App\Http\Controllers;

use App\Auth\Access\Gate;
use App\Http\Views;
use App\Models\AudioClipFeed;
use App\Models\Feed;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

        $feed->load([
            Feed::REL_SUBSCRIPTION,
            // Newest episode first, in the order the feed itself presents them, so that this page and the podcast app
            // reading the RSS agree about what's at the top.
            Feed::REL_AUDIO_CLIPS => function (BelongsToMany $q) {
                return $q->orderByPivot(AudioClipFeed::COL_PUBLISHED_AT, 'desc');
            },
        ]);

        return $views->feed($feed);
    }
}
