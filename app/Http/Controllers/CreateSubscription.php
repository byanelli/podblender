<?php

namespace App\Http\Controllers;

use App\Actions\FindOrCreateAudioSource;
use App\Http\Requests\CreateSubscriptionRequest;
use App\Jobs\UpdateSubscription;
use App\Models\Feed;
use App\Models\User;
use App\Platforms\Platforms;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Bus\Dispatcher;

readonly class CreateSubscription
{
    public function __invoke(
        Platforms $platforms,
        Dispatcher $dispatcher,
        FindOrCreateAudioSource $findOrCreateAudioSource,
        CreateSubscriptionRequest $request,
        #[CurrentUser] User $user,
    ): void {
        $platformType = $platforms->typeForUrl($request->url);

        $metadata = $platforms->for($platformType)->getSourceMetadata($request->url);

        $source = $findOrCreateAudioSource($platformType, $metadata);

        /** @var Feed $feed */
        $feed = $user->feeds()->create([
            Feed::COL_NAME => $request->name,
            Feed::COL_SUBSCRIPTION_ID => $source->id,
            Feed::COL_SUBSCRIBED_AT => now()->subMonth(), // todo make configurable
        ]);

        $dispatcher->dispatch(new UpdateSubscription($source, $feed));
    }
}
