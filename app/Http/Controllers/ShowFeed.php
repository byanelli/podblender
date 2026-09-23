<?php

namespace App\Http\Controllers;

use App\Auth\Access\Gate;
use App\Http\Views;
use App\Models\Feed;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
            'subscription',
            // Same order as the RSS: newest first by the pivot date.
            'audioClips'    => function (BelongsToMany $q) {
                return $q->orderByPivot('published_at', 'desc');
            },
            'inboundEmails' => fn (HasMany $q) => $q->latest('id')->limit(10),
        ]);

        return $views->feed($feed);
    }
}
