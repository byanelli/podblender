<?php

namespace App\Http\Controllers;

use App\Http\Views;
use App\Models\Feed;
use App\Models\User;
use App\Auth\AuthUserResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

readonly class Home
{
    public function __construct(
        private AuthUserResolver $authUserResolver,
        private Views $views,
    ) {}

    public function __invoke(Request $request): View {
        $user = $this->authUserResolver->get();

        $user->load(User::REL_FEEDS.'.'.Feed::REL_AUDIO_CLIPS_FINISHED_PROCESSING);

        return $this->views->home($user);
    }
}
