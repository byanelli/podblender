<?php

namespace App\Http\Controllers;

use App\Auth\Access\Gate;
use App\Http\Requests\AudioClipUrlRequest;
use App\Http\Views;
use App\Models\Feed;
use App\Platforms\PlatformFactory;
use App\Platforms\PlatformTypeResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\View\View;

readonly class ShowMetadata
{
    public function __construct(
        private PlatformTypeResolver $platformTypeResolver,
        private PlatformFactory $platformFactory,
        private Views $views,
        private Gate $gate,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(Feed $feed, AudioClipUrlRequest $request): View {
        $this->gate->authorizeUpdate($feed); // todo test authorization

        $url = $request->getUrl();

        try {
            $platformType = $this->platformTypeResolver->fromUrl($url);

            $platform = $this->platformFactory->make($platformType);

            $metadata = $platform->getMetadata($url);

            return $this->views->componentAddClipForm(
                feed: $feed,
                state: 'metadata',
                platformType: $platformType,
                metadata: $metadata,
                url: $url,
            );
        } catch (\Throwable $t) {
            return $this->views->componentAddClipForm(
                feed: $feed,
                state: 'error',
                error: $t->getMessage(),
            );
        }
    }
}
