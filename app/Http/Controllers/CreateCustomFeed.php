<?php

namespace App\Http\Controllers;

use App\Actions\GenerateFeedCover;
use App\Http\Requests\CreateCustomFeedRequest;
use App\Models\Feed;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

class CreateCustomFeed
{
    public function __invoke(
        #[CurrentUser] User $user,
        CreateCustomFeedRequest $request,
        GenerateFeedCover $generateCover,
    ): void {
        /** @var Feed $feed */
        $feed = $user->feeds()->create([
            'name' => $request->name,
        ]);

        // Drawn here rather than queued: it takes a fraction of a second, and
        // someone who copies the RSS link straight away should find artwork
        // already waiting. It never throws, so the feed exists either way.
        $generateCover($feed);
    }
}
