<?php

namespace App\Http;

use App\Models\Feed;
use Illuminate\Contracts\Routing\UrlGenerator;

class Urls
{
    public function __construct(private readonly UrlGenerator $urlGenerator) {}

    public function showFeed(Feed $feed): string {
        return $this->urlGenerator->route('showFeed', ['feed' => $feed], true);
    }
}
