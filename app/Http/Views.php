<?php

namespace App\Http;

use App\Enums\PlatformType;
use App\Models\Feed;
use App\Models\User;
use App\Platforms\Metadata;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\View;

readonly class Views
{
    public function __construct(private Factory $viewFactory) {}

    public function home(User $user): View {
        /* @see resources/views/home.blade.php */
        return $this->viewFactory->make('home', compact('user'));
    }

    public function feed(Feed $feed): View {
        /* @see resources/views/feed.blade.php */
        return $this->viewFactory->make('feed', compact('feed'));
    }

    public function componentAddClipForm(
        Feed $feed,
        string $state,
        ?PlatformType $platformType=null,
        ?string $error=null,
        ?Metadata $metadata=null,
        ?string $url=null,
    ): View {
        /** @see resources/views/components/addClipForm.blade.php */
        return $this->viewFactory->make(
            'components.addClipForm',
            compact('feed', 'state', 'platformType', 'error', 'metadata', 'url')
        );
    }

    public function rss(Feed $feed): View {
        /* @see resources/views/rss.blade.php */
        return $this->viewFactory->make('rss', compact('feed'));
    }
}
