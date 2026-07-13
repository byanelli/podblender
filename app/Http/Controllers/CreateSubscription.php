<?php

namespace App\Http\Controllers;

use App\Actions\FindOrCreateAudioSource;
use App\Http\Requests\CreateSubscriptionRequest;
use App\Jobs\UpdateSubscription;
use App\Models\Feed;
use App\Models\User;
use App\Platforms\Contracts\PlatformFactory;
use App\Platforms\PlatformTypeResolver;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Bus\Dispatcher;

readonly class CreateSubscription
{
    public function __invoke(
        PlatformTypeResolver $platformTypeResolver,
        PlatformFactory $platformFactory,
        Dispatcher $dispatcher,
        FindOrCreateAudioSource $findOrCreateAudioSource,
        CreateSubscriptionRequest $request,
        #[CurrentUser] User $user,
    ): void {
        $platformType = $platformTypeResolver->fromUrl($request->url);

        $platform = $platformFactory->make($platformType);

        $metadata = $platform->getSourceMetadata($request->url);

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
