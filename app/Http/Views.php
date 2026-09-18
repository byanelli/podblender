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
        /* @see resources/js/Pages/Dashboard.vue */
        return $this->inertiaPages->render('Dashboard', compact('user'));
    }

    public function feed(Feed $feed): Response
    {
        /* @see resources/js/Pages/Feed.vue */
        return $this->inertiaPages->render('Feed', compact('feed'));
    }

    /**
     * A feed's RSS, declared as XML rather than as a web page.
     *
     * The body has always been XML, but a bare view is sent as text/html,
     * which is the default Laravel gives any rendered template. The podcast
     * specs and the feed validators expect application/rss+xml, and a client
     * is free to be strict about what it accepts, so send the type the body
     * actually is rather than depend on clients being lenient.
     */
    public function rss(Feed $feed): HttpResponse
    {
        /* @see resources/views/rss.blade.php */
        return $this->responses->view('rss', compact('feed'), 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
        ]);
    }
}
