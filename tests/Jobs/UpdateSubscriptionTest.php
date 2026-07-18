<?php

namespace Tests\Jobs;

use App\Jobs\UpdateSubscription;
use App\Models\AudioClip;
use App\Models\AudioClipFeed;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\SourceMetadata;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Concerns\FakesDispatcher;
use Tests\Concerns\FakesPlatform;
use Tests\TestCase;

class UpdateSubscriptionTest extends TestCase
{
    use FakesDispatcher, FakesPlatform;

    private function sourceMetadataFor(AudioSource $subscription): SourceMetadata
    {
        return new SourceMetadata(
            name: $subscription->name,
            canonicalUrl: $subscription->platform_url,
        );
    }

    #[Test]
    public function it_initializes_a_subscription()
    {
        /** @var AudioSource $subscription */
        $subscription = AudioSource::factory()->create();
        $subscriber = Feed::factory()->create([
            'subscription_id' => $subscription->id,
            'subscribed_at' => now()->subDays(2),
        ]);

        $sourceMetadata = new SourceMetadata(
            name: $subscription->name,
            canonicalUrl: $subscription->platform_url,
        );

        $this->fakeNoOpDispatcher();

        $this->fakePlatform(clipMetadataList: [
            new ClipMetadata(
                title: 'Title 1',
                description: 'Description 1',
                canonicalUrl: $clip1Url = 'https://youtube.com/watch?v=clip1',
                publishedAt: now()->subDays(3), // published before subscription date -- should be missing in db
                source: $sourceMetadata,
            ),
            new ClipMetadata(
                title: $clip2Title = 'Title 2',
                description: $clip2Description = 'Description 2',
                canonicalUrl: $clip2Url = 'https://youtube.com/watch?v=clip2',
                publishedAt: $clip2PublishedAt = now()->subDays(2),
                source: $sourceMetadata,
            ),
            new ClipMetadata(
                title: $clip3Title = 'Title 3',
                description: $clip3Description = 'Description 3',
                canonicalUrl: $clip3Url = 'https://youtube.com/watch?v=clip3',
                publishedAt: $clip3PublishedAt = now()->subDays(1),
                source: $sourceMetadata,
            ),
        ]);

        $this->app->call([new UpdateSubscription($subscription, $subscriber), 'handle']);

        $this->assertDatabaseMissing('audio_clips', [
            'platform_url' => $clip1Url,
        ]);

        $this->assertDatabaseHas('audio_clips', [
            'title' => $clip2Title,
            'description' => $clip2Description,
            'platform_url' => $clip2Url,
            'published_at' => $clip2PublishedAt,
        ]);

        $this->assertDatabaseHas('audio_clips', [
            'title' => $clip3Title,
            'description' => $clip3Description,
            'platform_url' => $clip3Url,
            'published_at' => $clip3PublishedAt,
        ]);

        $this->assertDatabaseCount('audio_clips', 2);

        $this->assertEquals(2, $subscriber->audioClips()->count());
    }

    #[Test]
    public function it_presents_a_subscribed_clip_at_the_date_the_platform_published_it()
    {
        /** @var AudioSource $subscription */
        $subscription = AudioSource::factory()->create();
        $subscriber = Feed::factory()->create([
            Feed::COL_SUBSCRIPTION_ID => $subscription->id,
            Feed::COL_SUBSCRIBED_AT => CarbonImmutable::now()->subYears(2),
        ]);

        $this->fakeNoOpDispatcher();

        $this->fakePlatform(clipMetadataList: [
            new ClipMetadata(
                title: 'Lecture 1',
                description: 'Description',
                canonicalUrl: 'https://youtube.com/watch?v=clip1',
                publishedAt: $publishedAt = CarbonImmutable::now()->subYear()->roundSeconds(),
                source: new SourceMetadata(
                    name: $subscription->name,
                    canonicalUrl: $subscription->platform_url,
                ),
            ),
        ]);

        $this->app->call([new UpdateSubscription($subscription, $subscriber), 'handle']);

        /** @var AudioClip $clip */
        $clip = $subscriber->audioClips()->first();

        // Not the date it was downloaded, which is now: a lecture given a year ago is a year old in a subscription,
        // however long it took us to fetch it. It's what keeps a series in the order it was given.
        $this->assertEquals($publishedAt, $clip->pivot->published_at);
    }

    #[Test]
    public function it_backfills_clips_published_before_the_subscription_date()
    {
        /** @var AudioSource $subscription */
        $subscription = AudioSource::factory()->create();
        $subscriber = Feed::factory()->create([
            Feed::COL_SUBSCRIPTION_ID => $subscription->id,
            Feed::COL_SUBSCRIBED_AT => CarbonImmutable::now()->subDay(),
        ]);

        $this->fakeNoOpDispatcher();

        $this->fakePlatform(clipMetadataList: [
            new ClipMetadata(
                title: 'An old lecture',
                description: 'Description',
                canonicalUrl: 'https://youtube.com/watch?v=old',
                // Published long before anyone subscribed, so ordinarily this would be left out entirely.
                publishedAt: CarbonImmutable::now()->subMonths(6)->roundSeconds(),
                source: new SourceMetadata(
                    name: $subscription->name,
                    canonicalUrl: $subscription->platform_url,
                ),
            ),
        ]);

        $backfillSince = CarbonImmutable::now()->subYear();

        $this->app->call([new UpdateSubscription($subscription, $subscriber, $backfillSince), 'handle']);

        // Creating the clip isn't enough: it has to end up attached to the feed that asked for the backfill.
        $this->assertEquals(1, $subscriber->audioClips()->count());
    }

    #[Test]
    public function it_reaches_back_far_enough_for_a_lagging_subscriber_to_catch_up()
    {
        /** @var AudioSource $subscription */
        $subscription = AudioSource::factory()->create();

        // The subscriber that's been here longest and is fully caught up: it has a clip from just yesterday.
        $caughtUp = Feed::factory()->create([
            Feed::COL_SUBSCRIPTION_ID => $subscription->id,
            Feed::COL_SUBSCRIBED_AT => CarbonImmutable::now()->subDays(30),
        ]);
        /** @var AudioClip $recentClip */
        $recentClip = AudioClip::factory()->create([
            AudioClip::COL_AUDIO_SOURCE_ID => $subscription->id,
            AudioClip::COL_PUBLISHED_AT => CarbonImmutable::now()->subDay()->roundSeconds(),
        ]);
        $caughtUp->audioClips()->attach($recentClip, [
            AudioClipFeed::COL_PUBLISHED_AT => $recentClip->published_at,
        ]);

        // A subscriber that joined more recently and has no clips yet — its initial fill failed, say. Taking only the
        // earliest subscriber's newest clip as the cursor would leave this feed stranded forever.
        $lagging = Feed::factory()->create([
            Feed::COL_SUBSCRIPTION_ID => $subscription->id,
            Feed::COL_SUBSCRIBED_AT => CarbonImmutable::now()->subDays(10),
        ]);

        $this->fakeNoOpDispatcher();

        // A clip published between when the lagging subscriber joined and the caught-up subscriber's newest clip. The
        // old cursor (yesterday) would never fetch this; the fix reaches back to the lagging subscriber's join date.
        $this->fakePlatform(clipMetadataList: [
            new ClipMetadata(
                title: 'A clip the lagging subscriber missed',
                description: 'Description',
                canonicalUrl: 'https://youtube.com/watch?v=missed',
                publishedAt: CarbonImmutable::now()->subDays(5)->roundSeconds(),
                source: $this->sourceMetadataFor($subscription),
            ),
        ]);

        $this->app->call([new UpdateSubscription($subscription), 'handle']);

        // The lagging subscriber receives the missed clip...
        $this->assertEquals(1, $lagging->audioClips()->count());
        // ...and the caught-up subscriber picks it up too (it now has both).
        $this->assertEquals(2, $caughtUp->audioClips()->count());
    }

    #[Test]
    public function it_only_creates_clips_published_after_an_existing_clip()
    {
        /** @var AudioSource $subscription */
        $subscription = AudioSource::factory()->create();
        $subscriber = Feed::factory()->create([
            Feed::COL_SUBSCRIPTION_ID => $subscription->id,
            Feed::COL_SUBSCRIBED_AT => CarbonImmutable::now()->subYear(),
        ]);

        // The subscriber already has a clip from ten days ago; that's where the incremental cursor should start.
        /** @var AudioClip $existing */
        $existing = AudioClip::factory()->create([
            AudioClip::COL_AUDIO_SOURCE_ID => $subscription->id,
            AudioClip::COL_PUBLISHED_AT => CarbonImmutable::now()->subDays(10)->roundSeconds(),
        ]);
        $subscriber->audioClips()->attach($existing, [
            AudioClipFeed::COL_PUBLISHED_AT => $existing->published_at,
        ]);

        $this->fakeNoOpDispatcher();

        $this->fakePlatform(clipMetadataList: [
            new ClipMetadata(
                title: 'Older than the cursor',
                description: 'Description',
                canonicalUrl: $olderUrl = 'https://youtube.com/watch?v=older',
                publishedAt: CarbonImmutable::now()->subDays(20)->roundSeconds(),
                source: $this->sourceMetadataFor($subscription),
            ),
            new ClipMetadata(
                title: 'Newer than the cursor',
                description: 'Description',
                canonicalUrl: $newerUrl = 'https://youtube.com/watch?v=newer',
                publishedAt: CarbonImmutable::now()->subDays(5)->roundSeconds(),
                source: $this->sourceMetadataFor($subscription),
            ),
        ]);

        $this->app->call([new UpdateSubscription($subscription), 'handle']);

        // The clip published before the existing one is left alone; only the newer one is created.
        $this->assertDatabaseMissing('audio_clips', [AudioClip::COL_PLATFORM_URL => $olderUrl]);
        $this->assertDatabaseHas('audio_clips', [AudioClip::COL_PLATFORM_URL => $newerUrl]);
        $this->assertEquals(2, $subscriber->audioClips()->count());
    }

    #[Test]
    public function it_attaches_new_clips_to_every_subscriber()
    {
        /** @var AudioSource $subscription */
        $subscription = AudioSource::factory()->create();

        $subscribers = Feed::factory()->count(2)->create([
            Feed::COL_SUBSCRIPTION_ID => $subscription->id,
            Feed::COL_SUBSCRIBED_AT => CarbonImmutable::now()->subYear(),
        ]);

        $this->fakeNoOpDispatcher();

        $this->fakePlatform(clipMetadataList: [
            new ClipMetadata(
                title: 'Clip one',
                description: 'Description',
                canonicalUrl: 'https://youtube.com/watch?v=one',
                publishedAt: CarbonImmutable::now()->subDays(2)->roundSeconds(),
                source: $this->sourceMetadataFor($subscription),
            ),
            new ClipMetadata(
                title: 'Clip two',
                description: 'Description',
                canonicalUrl: 'https://youtube.com/watch?v=two',
                publishedAt: CarbonImmutable::now()->subDay()->roundSeconds(),
                source: $this->sourceMetadataFor($subscription),
            ),
        ]);

        $this->app->call([new UpdateSubscription($subscription), 'handle']);

        foreach ($subscribers as $subscriber) {
            $this->assertEquals(2, $subscriber->audioClips()->count());
        }
    }

    #[Test]
    public function it_does_nothing_when_a_source_has_no_subscribers()
    {
        /** @var AudioSource $subscription */
        $subscription = AudioSource::factory()->create();

        $this->fakePlatform(clipMetadataList: [
            new ClipMetadata(
                title: 'A clip nobody asked for',
                description: 'Description',
                canonicalUrl: 'https://youtube.com/watch?v=nobody',
                publishedAt: CarbonImmutable::now()->subDay()->roundSeconds(),
                source: $this->sourceMetadataFor($subscription),
            ),
        ]);

        $this->app->call([new UpdateSubscription($subscription), 'handle']);

        // No subscribers means no reason to fetch or create anything.
        $this->assertDatabaseCount('audio_clips', 0);
    }

    #[Test]
    public function it_rejects_an_explicit_subscriber_that_is_not_subscribed()
    {
        /** @var AudioSource $subscription */
        $subscription = AudioSource::factory()->create();

        // A real subscriber, so the source isn't empty and we reach the per-subscriber check.
        Feed::factory()->create([
            Feed::COL_SUBSCRIPTION_ID => $subscription->id,
            Feed::COL_SUBSCRIBED_AT => CarbonImmutable::now()->subYear(),
        ]);

        // A feed that is not subscribed to this source at all.
        $stranger = Feed::factory()->create();

        $this->expectException(RuntimeException::class);

        $this->app->call([new UpdateSubscription($subscription, $stranger), 'handle']);
    }

    #[Test]
    public function its_unique_id_is_the_source_id()
    {
        /** @var AudioSource $subscription */
        $subscription = AudioSource::factory()->create();

        // One update per source at a time, so an overlapping scheduler tick can't run two at once.
        $this->assertEquals((string) $subscription->id, (new UpdateSubscription($subscription))->uniqueId());
    }
}
