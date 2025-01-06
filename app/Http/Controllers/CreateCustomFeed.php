<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCustomFeedRequest;
use App\Models\Feed;
use Illuminate\Container\Attributes\CurrentUser;

class CreateCustomFeed
{
    public function __invoke(
        #[CurrentUser] $user,
        CreateCustomFeedRequest $request,
    ): void {
        $user->feeds()->create([
            Feed::COL_NAME => $request->getFeedName(),
        ]);
    }
}
