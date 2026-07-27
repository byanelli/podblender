<?php

namespace Tests\Http\Controllers;

use App\Models\AudioSource;
use App\Models\Feed;
use App\Models\User;
use App\Platforms\Contracts\SourceMetadata;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\FakesPlatform;
use Tests\TestCase;

class CreateSubscriptionTest extends TestCase
{
    use FakesPlatform;

    public function test_create_subscription()
    {
        $sourceUrl = 'https://youtube.com/@zzz';
        $feedName = 'Test Feed';
        $user = User::factory()->create();
        $this->actingAs($user);

        $requestPayload = [
            'url' => $sourceUrl,
            'name' => $feedName,
        ];

        $this->fakePlatform(
            sourceMetadata: new SourceMetadata(
                name: $sourceName = 'Test channel',
                canonicalUrl: $sourceUrl,
            )
        );

        Bus::fake();

        $this->postJson('/feeds/subscription', $requestPayload)
            ->assertOk();

        $this->assertDatabaseCount('audio_sources', 1);

        $this->assertDatabaseHas('audio_sources', [
            'platform_url' => $sourceUrl,
            'name' => $sourceName,
        ]);

        $this->assertDatabaseCount('feeds', 1);

        $this->assertDatabaseHas('feeds', [
            'name' => $feedName,
            'user_id' => $user->id,
            'subscription_id' => AudioSource::first()->id,
        ]);
    }

    public function test_backfill_window_is_configurable()
    {
        // The window a new subscription reaches back over is config-driven, not the hardcoded one month it used to be.
        config(['subscriptions.backfill_months' => 3]);

        $this->travelTo($now = CarbonImmutable::parse('2026-05-06 07:08:09'));

        $user = User::factory()->create();
        $this->actingAs($user);

        $this->fakePlatform(
            sourceMetadata: new SourceMetadata(
                name: 'Test channel',
                canonicalUrl: $sourceUrl = 'https://youtube.com/@zzz',
            )
        );

        Bus::fake();

        $this->postJson('/feeds/subscription', ['url' => $sourceUrl, 'name' => 'Test Feed'])
            ->assertOk();

        /** @var Feed $feed */
        $feed = Feed::first();

        // Three months back from now, per the config override.
        $this->assertEquals($now->subMonths(3), $feed->backfill_since);

        // ...and the subscription date records when they actually subscribed. These used to be the same column, so
        // the recorded subscription date was silently a backfill window and no screen could show the real one.
        $this->assertEquals($now, $feed->subscribed_at);
    }

    public function test_a_subscription_can_reach_back_to_a_chosen_date()
    {
        $this->travelTo($now = CarbonImmutable::parse('2026-05-06 07:08:09'));

        $this->actingAs(User::factory()->create());

        $this->fakePlatform(sourceMetadata: new SourceMetadata(
            name: 'Test channel',
            canonicalUrl: $sourceUrl = 'https://youtube.com/@zzz',
        ));

        Bus::fake();

        $this->postJson('/feeds/subscription', [
            'url' => $sourceUrl,
            'name' => 'Test Feed',
            // The epoch is how "everything ever published" is expressed.
            'backfillSince' => '1970-01-01T00:00:00+00:00',
        ])->assertOk();

        /** @var Feed $feed */
        $feed = Feed::first();

        $this->assertEquals(CarbonImmutable::parse('1970-01-01T00:00:00+00:00'), $feed->backfill_since);
        $this->assertEquals($now, $feed->subscribed_at);
        $this->assertTrue($feed->tracks_new_episodes);
    }

    public function test_a_subscription_can_decline_future_episodes()
    {
        $this->actingAs(User::factory()->create());

        $this->fakePlatform(sourceMetadata: new SourceMetadata(
            name: 'Test channel',
            canonicalUrl: $sourceUrl = 'https://youtube.com/@zzz',
        ));

        Bus::fake();

        $this->postJson('/feeds/subscription', [
            'url' => $sourceUrl,
            'name' => 'Test Feed',
            'tracksNewEpisodes' => false,
        ])->assertOk();

        /** @var Feed $feed */
        $feed = Feed::first();

        $this->assertFalse($feed->tracks_new_episodes);

        // Not filled yet — that happens when the initial update runs, and until
        // then the feed still needs its one sweep.
        $this->assertNull($feed->subscription_filled_at);
        $this->assertTrue($feed->needsUpdating());
    }
}
