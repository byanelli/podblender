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
use Illuminate\Support\Facades\DB;

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

        // Fetch the source metadata before opening the transaction: it's a network call to the platform, and holding a
        // database transaction open across it would be pointless and slow.
        $metadata = $platforms->for($platformType)->getSourceMetadata($request->url);

        // Find-or-create the source, create the feed, and queue its initial fill as one unit. If any step throws, we
        // don't want a half-made subscription left behind — a feed with no source, or a source and feed with no job to
        // fill them in.
        DB::transaction(function () use ($dispatcher, $findOrCreateAudioSource, $platformType, $metadata, $request, $user) {
            $source = $findOrCreateAudioSource($platformType, $metadata);

            /** @var Feed $feed */
            $feed = $user->feeds()->create([
                Feed::COL_NAME => $request->name,
                Feed::COL_SUBSCRIPTION_ID => $source->id,
                // A new subscription reaches back over a configurable window (one month by default) so the platform's
                // recent back catalogue shows up straight away rather than only clips published from now on.
                Feed::COL_SUBSCRIBED_AT => now()->subMonths(config('subscriptions.backfill_months')),
            ]);

            $dispatcher->dispatch(new UpdateSubscription($source, $feed));
        });
    }
}
