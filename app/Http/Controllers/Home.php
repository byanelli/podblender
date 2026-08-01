<?php

namespace App\Http\Controllers;

use App\Http\Views;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Inertia\Response;

readonly class Home
{
    public function __invoke(
        Views $views,
        Request $request,
        #[CurrentUser] User $user,
    ): Response {
        $user->load([
            User::REL_FEEDS => function (HasMany $feeds) {
                return $feeds->withCount(
                    Feed::REL_AUDIO_CLIPS
                )->with(
                    Feed::REL_SUBSCRIPTION
                    .':'.AudioSource::COL_ID
                    .','.AudioSource::COL_PLATFORM_URL
                );
            },
        ]);

        return $views->home($user);
    }
}
