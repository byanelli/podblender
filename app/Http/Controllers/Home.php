<?php

namespace App\Http\Controllers;

use App\Http\Views;
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
            'feeds' => fn (HasMany $feeds) => $feeds
                ->withCount('audioClips')
                ->with('subscription:id,platform_url'),
        ]);

        return $views->home($user);
    }
}
