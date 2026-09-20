<?php

namespace Tests\Http\Controllers;

use App\Models\AudioSource;
use App\Models\Feed;
use App\Models\User;
use App\Platforms\Contracts\SourceMetadata;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesCoverGenerator;
use Tests\Concerns\FakesPlatform;
use Tests\TestCase;

class CreateSubscriptionTest extends TestCase
{
    use FakesCoverGenerator, FakesPlatform;

    protected function setUp(): void
    {
        parent::setUp();

        // Creating a feed draws a cover. No test here inspects the image, so
        // the generator is faked.
        Storage::fake();
        $this->fakeCoverGenerator();
    }

    public function test_create_subscription()
    {
        $sourceUrl = 'https://youtube.com/@zzz';
        $feedName = 'Test Feed';
        $user = User::factory()->create();
        $this->actingAs($user);

        $requestPayload = [
            'url'  => $sourceUrl,
            'name' => $feedName,
        ];

        $this->fakePlatform(
            sourceMetadata: new SourceMetadata(
                name: $sourceName = 'Test channel',
                canonicalUrl: $sourceUrl,
                authorName: $sourceName,
            )
        );

        Bus::fake();

        $this->postJson('/feeds/subscription', $requestPayload)
            ->assertOk();

        $this->assertDatabaseCount('audio_sources', 1);

        $this->assertDatabaseHas('audio_sources', [
            'platform_url' => $sourceUrl,
            'name'         => $sourceName,
        ]);

        $this->assertDatabaseCount('feeds', 1);

        $this->assertDatabaseHas('feeds', [
            'name'            => $feedName,
            'user_id'         => $user->id,
            'subscription_id' => AudioSource::first()->id,
        ]);
    }

    public function test_backfill_window_is_configurable()
    {
        config(['subscriptions.backfill_months' => 3]);

        $this->travelTo($now = CarbonImmutable::parse('2026-05-06 07:08:09'));

        $user = User::factory()->create();
        $this->actingAs($user);

        $this->fakePlatform(
            sourceMetadata: new SourceMetadata(
                name: 'Test channel',
                canonicalUrl: $sourceUrl = 'https://youtube.com/@zzz',
                authorName: 'Test channel',
            )
        );

        Bus::fake();

        $this->postJson('/feeds/subscription', ['url' => $sourceUrl, 'name' => 'Test Feed'])
            ->assertOk();

        /** @var Feed $feed */
        $feed = Feed::first();

        $this->assertEquals($now->subMonths(3), $feed->backfill_since);

        // The subscription date is separate from the backfill window.
        $this->assertEquals($now, $feed->subscribed_at);
    }

    public function test_a_subscription_can_reach_back_to_a_chosen_date()
    {
        $this->travelTo($now = CarbonImmutable::parse('2026-05-06 07:08:09'));

        $this->actingAs(User::factory()->create());

        $this->fakePlatform(sourceMetadata: new SourceMetadata(
            name: 'Test channel',
            canonicalUrl: $sourceUrl = 'https://youtube.com/@zzz',
            authorName: 'Test channel',
        ));

        Bus::fake();

        $this->postJson('/feeds/subscription', [
            'url'           => $sourceUrl,
            'name'          => 'Test Feed',
            // The epoch means "everything ever published".
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
            authorName: 'Test channel',
        ));

        Bus::fake();

        $this->postJson('/feeds/subscription', [
            'url'               => $sourceUrl,
            'name'              => 'Test Feed',
            'tracksNewEpisodes' => false,
        ])->assertOk();

        /** @var Feed $feed */
        $feed = Feed::first();

        $this->assertFalse($feed->tracks_new_episodes);

        // The feed is marked filled when the initial update runs. Until then
        // it still needs updating.
        $this->assertNull($feed->subscription_filled_at);
        $this->assertTrue($feed->needsUpdating());
    }

    #[Test]
    public function it_gives_the_new_feed_a_cover()
    {
        $storage = Storage::fake();

        $this->actingAs(User::factory()->create());

        $this->fakePlatform(sourceMetadata: new SourceMetadata(
            name: 'Test channel',
            canonicalUrl: $sourceUrl = 'https://youtube.com/@zzz',
            authorName: 'Test channel',
        ));

        Bus::fake();

        $this->postJson('/feeds/subscription', ['url' => $sourceUrl, 'name' => 'Adam Tooze'])
            ->assertOk();

        $feed = Feed::query()->sole();

        $this->assertNotNull($feed->cover_path);
        $storage->assertExists($feed->cover_path);
    }

    #[Test]
    public function a_cover_that_cannot_be_drawn_does_not_stop_the_subscription_being_created()
    {
        $this->fakeCoverGeneratorThatFails('the cover font is missing');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message) => str_contains($message, 'the cover font is missing'));

        $this->actingAs(User::factory()->create());

        $this->fakePlatform(sourceMetadata: new SourceMetadata(
            name: 'Test channel',
            canonicalUrl: $sourceUrl = 'https://youtube.com/@zzz',
            authorName: 'Test channel',
        ));

        Bus::fake();

        $this->postJson('/feeds/subscription', ['url' => $sourceUrl, 'name' => 'Adam Tooze'])
            ->assertOk();

        $this->assertNull(Feed::query()->sole()->cover_path);
        $this->assertDatabaseCount('feeds', 1);
    }
}
