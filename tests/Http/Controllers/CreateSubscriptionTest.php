<?php

namespace Tests\Http\Controllers;

use App\Models\AudioSource;
use App\Models\Feed;
use App\Models\User;
use App\Platforms\Contracts\SourceMetadata;
use Carbon\CarbonImmutable;
use Tests\Concerns\FakesDispatcher;
use Tests\Concerns\FakesPlatform;
use Tests\TestCase;

class CreateSubscriptionTest extends TestCase
{
    use FakesDispatcher, FakesPlatform;

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

        $this->fakeNoOpDispatcher();

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

        $this->fakeNoOpDispatcher();

        $this->postJson('/feeds/subscription', ['url' => $sourceUrl, 'name' => 'Test Feed'])
            ->assertOk();

        /** @var Feed $feed */
        $feed = Feed::first();

        // Three months back from now, per the config override.
        $this->assertEquals($now->subMonths(3), $feed->subscribed_at);
    }
}
