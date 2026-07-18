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

        Feed::factory()->create([Feed::COL_SUBSCRIPTION_ID => $subscribedA->id]);
        Feed::factory()->create([Feed::COL_SUBSCRIPTION_ID => $subscribedB->id]);

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
    public function it_skips_sources_that_have_no_subscribers()
    {
        Bus::fake();

        // A source with a subscriber, and one without.
        $subscribed = AudioSource::factory()->create();
        Feed::factory()->create([Feed::COL_SUBSCRIPTION_ID => $subscribed->id]);

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
