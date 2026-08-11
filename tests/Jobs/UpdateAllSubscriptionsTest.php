<?php

namespace Tests\Jobs;

use App\Jobs\UpdateAllSubscriptions;
use App\Jobs\UpdateSubscription;
use App\Models\AudioSource;
use App\Models\Feed;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateAllSubscriptionsTest extends TestCase
{
    #[Test]
    public function it_dispatches_an_update_for_every_source_that_has_subscribers()
    {
        Bus::fake();

        $subscribedA = AudioSource::factory()->create();
        $subscribedB = AudioSource::factory()->create();

        Feed::factory()->create(['subscription_id' => $subscribedA->id]);
        Feed::factory()->create(['subscription_id' => $subscribedB->id]);

        $this->app->call([new UpdateAllSubscriptions, 'handle']);

        Bus::assertDispatchedTimes(UpdateSubscription::class, 2);

        foreach ([$subscribedA, $subscribedB] as $source) {
            Bus::assertDispatched(
                UpdateSubscription::class,
                fn (UpdateSubscription $job) => $job->uniqueId() === (string) $source->id,
            );
        }
    }

    #[Test]
    public function it_skips_a_source_whose_subscribers_have_all_had_their_one_fill()
    {
        Bus::fake();

        // A one-shot subscriber captured this source and has been filled, so
        // there's nothing left to collect. Sweeping it every couple of hours
        // would spend platform quota forever on a feed nobody wants updated.
        $finished = AudioSource::factory()->create();
        Feed::factory()->create([
            'subscription_id'        => $finished->id,
            'tracks_new_episodes'    => false,
            'subscription_filled_at' => now()->subDay(),
        ]);

        $this->app->call([new UpdateAllSubscriptions, 'handle']);

        Bus::assertNotDispatched(UpdateSubscription::class);
    }

    #[Test]
    public function it_still_sweeps_a_one_shot_subscription_that_has_not_been_filled_yet()
    {
        Bus::fake();

        // Declining future episodes doesn't mean declining the first fill —
        // that sweep is what populates the feed in the first place.
        $pending = AudioSource::factory()->create();
        Feed::factory()->create([
            'subscription_id'        => $pending->id,
            'tracks_new_episodes'    => false,
            'subscription_filled_at' => null,
        ]);

        $this->app->call([new UpdateAllSubscriptions, 'handle']);

        Bus::assertDispatchedTimes(UpdateSubscription::class, 1);
    }

    #[Test]
    public function it_sweeps_a_source_that_still_has_one_interested_subscriber()
    {
        Bus::fake();

        // Two subscribers to one source disagreeing about future episodes: the
        // finished one mustn't switch the other one off.
        $source = AudioSource::factory()->create();

        Feed::factory()->create([
            'subscription_id'        => $source->id,
            'tracks_new_episodes'    => false,
            'subscription_filled_at' => now()->subDay(),
        ]);

        Feed::factory()->create([
            'subscription_id'     => $source->id,
            'tracks_new_episodes' => true,
        ]);

        $this->app->call([new UpdateAllSubscriptions, 'handle']);

        Bus::assertDispatchedTimes(UpdateSubscription::class, 1);
    }

    #[Test]
    public function it_skips_sources_that_have_no_subscribers()
    {
        Bus::fake();

        // A source with a subscriber, and one without.
        $subscribed = AudioSource::factory()->create();
        Feed::factory()->create(['subscription_id' => $subscribed->id]);

        $unsubscribed = AudioSource::factory()->create();

        $this->app->call([new UpdateAllSubscriptions, 'handle']);

        Bus::assertDispatchedTimes(UpdateSubscription::class, 1);
        Bus::assertDispatched(
            UpdateSubscription::class,
            fn (UpdateSubscription $job) => $job->uniqueId() === (string) $subscribed->id,
        );
        Bus::assertNotDispatched(
            UpdateSubscription::class,
            fn (UpdateSubscription $job) => $job->uniqueId() === (string) $unsubscribed->id,
        );
    }
}
