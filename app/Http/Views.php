<?php

namespace App\Http;

use App\Models\Feed;
use App\Models\User;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Inertia\Response;
use Inertia\ResponseFactory;

readonly class Views
{
    public function __construct(
        private Factory $viewFactory,
        private ResponseFactory $inertiaPages,
    ) {}

    public function home(User $user): Response {
        /* @see resources/js/Pages/Dashboard.vue */
        return $this->inertiaPages->render('Dashboard', compact('user'));
    }

    public function feed(Feed $feed): Response {
        /* @see resources/js/Pages/Feed.vue */
        return $this->inertiaPages->render('Feed', compact('feed'));
    }

    public function rss(Feed $feed): View {
        /* @see resources/views/rss.blade.php */
        return $this->viewFactory->make('rss', compact('feed'));
    }
}
