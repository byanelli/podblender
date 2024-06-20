<?php

namespace App\Http\Controllers;

use App\Auth\AuthUserResolver;
use App\Http\Views;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Response;

readonly class Home
{
    public function __construct(
        private AuthUserResolver $authUserResolver,
        private Views $views,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->authUserResolver->get();

        $user->load(User::REL_FEEDS);

        return $this->views->home($user);
    }
}
