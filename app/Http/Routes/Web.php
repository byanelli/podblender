<?php

namespace App\Http\Routes;

use App\Models\Feed;

/**
 * @see routes/web.php
 */
abstract class Web
{
    public static function rss(Feed $feed): string {
        return route('rss', [$feed]);
    }

    public static function showLogin(): string {
        return route('showLogin');
    }

    public static function attemptLogin(): string {
        return route('attemptLogin');
    }

    public static function home(): string {
        return route('home');
    }

    public static function logout(): string {
        return route('logout');
    }

    public static function showFeed(Feed $feed): string {
        return route('showFeed', [$feed]);
    }

    public static function addClipToFeed(Feed $feed, ?string $url = null): string {
        return route('addClipToFeed', [$feed]);
    }

    public static function showMetadata(Feed $feed): string {
        return route('showMetadata', [$feed]);
    }
}
