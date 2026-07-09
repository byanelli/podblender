<?php

namespace App\Http\Controllers;

use App\Actions\FindOrCreateAudioSource;
use App\Http\Requests\CreateSubscriptionRequest;
use App\Jobs\UpdateSubscription;
use App\Models\Feed;
use App\Models\User;
use App\Platforms\Contracts\PlatformFactory;
use App\Platforms\PlatformTypeResolver;
use BYanelli\Roma\Request\ContextualBinding\Request;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Bus\Dispatcher;

readonly class CreateSubscription
{
    public function __invoke(
        PlatformTypeResolver $platformTypeResolver,
        PlatformFactory $platformFactory,
        Dispatcher $dispatcher,
        FindOrCreateAudioSource $findOrCreateAudioSource,
        #[Request] CreateSubscriptionRequest $request,
        #[CurrentUser] User $user,
    ): void {
        $url = $request->url;

        $platformType = $platformTypeResolver->fromUrl($url);

        $platform = $platformFactory->make($platformType);

        $metadata = $platform->getSourceMetadata($url);

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
