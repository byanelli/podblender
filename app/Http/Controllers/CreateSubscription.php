<?php

namespace App\Http\Controllers;

use App\Actions\FindOrCreateAudioSource;
use App\Actions\GenerateFeedCover;
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
        GenerateFeedCover $generateCover,
        CreateSubscriptionRequest $request,
        #[CurrentUser] User $user,
    ): void {
        $platformType = $platforms->subscribableTypeForUrl($request->url);

        // A network call, so it runs before the transaction opens.
        $metadata = $platforms->for($platformType)->getSourceMetadata($request->url);

        // One transaction, so a failure can't leave a feed without a source or without the job that fills it.
        $feed = DB::transaction(function () use ($dispatcher, $findOrCreateAudioSource, $platformType, $metadata, $request, $user): Feed {
            $source = $findOrCreateAudioSource($platformType, $metadata);

            /** @var Feed $feed */
            $feed = $user->feeds()->create([
                'name'                => $request->name,
                'subscription_id'     => $source->id,
                'subscribed_at'       => now(),
                // Defaults to a configured window (one month unless overridden), so a new feed starts with the
                // source's recent clips.
                'backfill_since'      => $request->backfillSince
                    ?? now()->subMonths(config('subscriptions.backfill_months')),
                'tracks_new_episodes' => $request->tracksNewEpisodes,
            ]);

            $dispatcher->dispatch(new UpdateSubscription($source, $feed));

            return $feed;
        });

        // Runs after commit so a rollback can't leave a cover file with no feed. Never throws.
        $generateCover($feed);
    }
}
