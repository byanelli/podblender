<?php

namespace App\Http;

use App\Models\Feed;
use App\Models\User;
use Illuminate\Contracts\Routing\ResponseFactory as Responses;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Response;
use Inertia\ResponseFactory;

readonly class Views
{
    public function __construct(
        private ResponseFactory $inertiaPages,
        private Responses $responses,
    ) {}

    public function home(User $user): Response
    {
        /* @see resources/js/Pages/Dashboard.tsx */
        return $this->inertiaPages->render('Dashboard', compact('user'));
    }

    public function feed(Feed $feed): Response
    {
        /* @see resources/js/Pages/Feed.tsx */
        return $this->inertiaPages->render('Feed', compact('feed'));
    }

    /**
     * A feed's RSS. Laravel sends a rendered view as text/html by default;
     * the podcast specs and feed validators expect application/rss+xml.
     */
    public function rss(Feed $feed): HttpResponse
    {
        /* @see resources/views/rss.blade.php */
        return $this->responses->view('rss', compact('feed'), 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
        ]);
    }
}
