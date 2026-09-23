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

        $feed->regenerateInboundEmailToken();

        // Runs inline because it takes a fraction of a second, and the RSS
        // link may be copied straight away. Never throws.
        $generateCover($feed);
    }
}
