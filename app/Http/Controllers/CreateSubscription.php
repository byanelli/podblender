<?php

namespace App\Http\Controllers;

use App\Auth\AuthUserResolver;
use App\Http\Requests\SubscriptionCreateRequest;
use App\Jobs\UpdateSubscription;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Platforms\PlatformFactory;
use App\Platforms\PlatformTypeResolver;
use Illuminate\Contracts\Bus\Dispatcher;

readonly class CreateSubscription
{
    public function __construct(
        private PlatformTypeResolver $platformTypeResolver,
        private PlatformFactory $platformFactory,
        private AuthUserResolver $authUserResolver,
        private Dispatcher $dispatcher,
    ) {}

    public function __invoke(SubscriptionCreateRequest $request)
    {
        $url = $request->getUrl();

        $platformType = $this->platformTypeResolver->fromUrl($url);

        $platform = $this->platformFactory->make($platformType);

        $metadata = $platform->getSourceMetadata($url);

        /** @var AudioSource $source */
        $source = AudioSource::query()->firstOrCreate(
            [
                AudioSource::COL_PLATFORM_TYPE => $platformType,
                AudioSource::COL_PLATFORM_URL  => $metadata->canonicalUrl,
            ],
            [
                AudioSource::COL_PLATFORM_TYPE => $platformType,
                AudioSource::COL_PLATFORM_URL  => $metadata->canonicalUrl,
                AudioSource::COL_NAME          => $metadata->name,
            ]
        );

        $user = $this->authUserResolver->get();

        /** @var Feed $feed */
        $feed = $user->feeds()->create([
            Feed::COL_NAME => $request->getFeedName(),
            Feed::COL_SUBSCRIPTION_ID => $source->id,
            Feed::COL_SUBSCRIBED_AT => now()->subWeek(), // todo make configurable
        ]);

        $this->dispatcher->dispatch(new UpdateSubscription($source, $feed));
    }
}
