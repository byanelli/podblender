<?php

namespace Tests\Jobs;

use App\Jobs\UpdateSubscription;
use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\SourceMetadata;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Concerns\FakesPlatform;
use Tests\TestCase;

class UpdateSubscriptionTest extends TestCase
{
    use FakesPlatform;

    private function sourceMetadataFor(AudioSource $subscription): SourceMetadata
    {
        return new SourceMetadata(
            name: $subscription->name,
            canonicalUrl: $subscription->platform_url,
            authorName: $subscription->name,
        );
    }

    #[Test]
    public function it_initializes_a_subscription()
    {
        /** @var AudioSource $subscription */
        $subscription = AudioSource::factory()->create();
        $subscriber = Feed::factory()->create([
            'subscription_id' => $subscription->id,
            'subscribed_at'   => now()->subDays(2),
        ]);

        $sourceMetadata = new SourceMetadata(
            name: $subscription->name,
            canonicalUrl: $subscription->platform_url,
            authorName: $subscription->name,
        );

        Bus::fake();

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
            'title'        => $clip2Title,
            'description'  => $clip2Description,
            'platform_url' => $clip2Url,
            'published_at' => $clip2PublishedAt,
        ]);

        $this->assertDatabaseHas('audio_clips', [
            'title'        => $clip3Title,
            'description'  => $clip3Description,
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
            'subscription_id' => $subscription->id,
            'subscribed_at'   => CarbonImmutable::now()->subYears(2),
        ]);

        Bus::fake();

        $this->fakePlatform(clipMetadataList: [
            new ClipMetadata(
                title: 'Lecture 1',
                description: 'Description',
                canonicalUrl: 'https://youtube.com/watch?v=clip1',
                publishedAt: $publishedAt = CarbonImmutable::now()->subYear()->roundSeconds(),
                source: new SourceMetadata(
                    name: $subscription->name,
                    canonicalUrl: $subscription->platform_url,
                    authorName: $subscription->name,
                ),
            ),
        ]);

        $this->app->call([new UpdateSubscription($subscription, $subscriber), 'handle']);

        /** @var AudioClip $clip */
        $clip = $subscriber->audioClips()->first();

        // The platform's publication date, not the download date, so a series stays in the order it was published.
        $this->assertEquals($publishedAt, $clip->pivot->published_at);
    }

    #[Test]
    public function it_backfills_clips_published_before_the_subscription_date()
    {
        /** @var AudioSource $subscription */
        $subscription = AudioSource::factory()->create();
        $subscriber = Feed::factory()->create([
            'subscription_id' => $subscription->id,
            'subscribed_at'   => CarbonImmutable::now()->subDay(),
        ]);

        Bus::fake();

        $this->fakePlatform(clipMetadataList: [
            new ClipMetadata(
                title: 'An old lecture',
                description: 'Description',
                canonicalUrl: 'https://youtube.com/watch?v=old',
                // Published before the subscription date, so only the backfill includes it.
                publishedAt: CarbonImmutable::now()->subMonths(6)->roundSeconds(),
                source: new SourceMetadata(
                    name: $subscription->name,
                    canonicalUrl: $subscription->platform_url,
                    authorName: $subscription->name,
                ),
            ),
        ]);

        $backfillSince = CarbonImmutable::now()->subYear();

        $this->app->call([new UpdateSubscription($subscription, $subscriber, $backfillSince), 'handle']);

        // The clip must also be attached to the feed that requested the backfill.
        $this->assertEquals(1, $subscriber->audioClips()->count());
    }

    #[Test]
    public function it_uses_the_subscribers_chosen_backfill_window()
    {
        /** @var AudioSource $subscription */
        $subscription = AudioSource::factory()->create();

        // Subscribed now, with a backfill window of a year. Filtering by the
        // subscription date would leave this feed empty.
        $subscriber = Feed::factory()->create([
            'subscription_id' => $subscription->id,
            'subscribed_at'   => CarbonImmutable::now(),
            'backfill_since'  => CarbonImmutable::now()->subYear(),
        ]);

        Bus::fake();

        $this->fakePlatform(clipMetadataList: [
            new ClipMetadata(
                title: 'A lecture from six months ago',
                description: 'Description',
                canonicalUrl: 'https://youtube.com/watch?v=old',
                publishedAt: CarbonImmutable::now()->subMonths(6)->roundSeconds(),
                source: $this->sourceMetadataFor($subscription),
            ),
        ]);

        $this->app->call([new UpdateSubscription($subscription, $subscriber), 'handle']);

        $this->assertEquals(1, $subscriber->audioClips()->count());
    }

    #[Test]
    public function it_marks_a_one_shot_subscription_filled_once_it_has_run()
    {
        /** @var AudioSource $subscription */
        $subscription = AudioSource::factory()->create();

        $subscriber = Feed::factory()->create([
            'subscription_id'     => $subscription->id,
            'subscribed_at'       => CarbonImmutable::now(),
            'backfill_since'      => CarbonImmutable::now()->subYear(),
            'tracks_new_episodes' => false,
        ]);

        Bus::fake();

        $this->fakePlatform(clipMetadataList: [
            new ClipMetadata(
                title: 'The only episode',
                description: 'Description',
                canonicalUrl: 'https://youtube.com/watch?v=one',
                publishedAt: CarbonImmutable::now()->subMonth()->roundSeconds(),
                source: $this->sourceMetadataFor($subscription),
            ),
        ]);

        $this->app->call([new UpdateSubscription($subscription, $subscriber), 'handle']);

        $subscriber->refresh();

        $this->assertEquals(1, $subscriber->audioClips()->count());

        // Once filled, it is excluded from later sweeps.
        $this->assertNotNull($subscriber->subscription_filled_at);
        $this->assertFalse($subscriber->needsUpdating());
    }

    #[Test]
    public function it_leaves_a_tracking_subscription_open_after_an_update()
    {
        /** @var AudioSource $subscription */
        $subscription = AudioSource::factory()->create();

        $subscriber = Feed::factory()->create([
            'subscription_id'     => $subscription->id,
            'subscribed_at'       => CarbonImmutable::now()->subDay(),
            'tracks_new_episodes' => true,
        ]);

        Bus::fake();

        $this->fakePlatform(clipMetadataList: []);

        $this->app->call([new UpdateSubscription($subscription, $subscriber), 'handle']);

        $this->assertNull($subscriber->refresh()->subscription_filled_at);
        $this->assertTrue($subscriber->needsUpdating());
    }

    #[Test]
    public function it_does_not_let_a_finished_one_shot_drag_the_fetch_cursor_backwards()
    {
        /** @var AudioSource $subscription */
        $subscription = AudioSource::factory()->create();

        // A filled one-shot whose backfill window starts in 2014. If it counted
        // towards the cursor, every sweep of this source would re-fetch a decade
        // of clips.
        Feed::factory()->create([
            'subscription_id'        => $subscription->id,
            'subscribed_at'          => CarbonImmutable::now()->subDays(2),
            'backfill_since'         => CarbonImmutable::parse('2014-01-01'),
            'tracks_new_episodes'    => false,
            'subscription_filled_at' => CarbonImmutable::now()->subDay(),
        ]);

        // An ordinary subscriber that joined yesterday.
        Feed::factory()->create([
            'subscription_id' => $subscription->id,
            'subscribed_at'   => $subscribedAt = CarbonImmutable::now()->subDay(),
            'backfill_since'  => $subscribedAt,
        ]);

        Bus::fake();

        $this->fakePlatform(clipMetadataList: []);

        $this->app->call([new UpdateSubscription($subscription), 'handle']);

        // The platform was asked for clips since yesterday, not since 2014.
        $this->assertEquals(
            $subscribedAt->timestamp,
            $this->platformPublicationTimeRequested()?->getTimestamp(),
        );
    }

    #[Test]
    public function it_reaches_back_far_enough_for_a_lagging_subscriber_to_catch_up()
    {
        /** @var AudioSource $subscription */
        $subscription = AudioSource::factory()->create();

        // The earliest subscriber is caught up, with a clip from yesterday.
        $caughtUp = Feed::factory()->create([
            'subscription_id' => $subscription->id,
            'subscribed_at'   => CarbonImmutable::now()->subDays(30),
        ]);
        /** @var AudioClip $recentClip */
        $recentClip = AudioClip::factory()->create([
            'audio_source_id' => $subscription->id,
            'published_at'    => CarbonImmutable::now()->subDay()->roundSeconds(),
        ]);
        $caughtUp->audioClips()->attach($recentClip, [
            'published_at' => $recentClip->published_at,
        ]);

        // A later subscriber with no clips, e.g. because its initial fill failed. Using only the earliest subscriber's
        // newest clip as the cursor would never fill this feed.
        $lagging = Feed::factory()->create([
            'subscription_id' => $subscription->id,
            'subscribed_at'   => CarbonImmutable::now()->subDays(10),
        ]);

        Bus::fake();

        // Published after the lagging subscriber joined and before the caught-up subscriber's newest clip. A cursor of
        // yesterday would not fetch it.
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

        $this->assertEquals(1, $lagging->audioClips()->count());
        // The caught-up subscriber is attached to it as well, so it has two.
        $this->assertEquals(2, $caughtUp->audioClips()->count());
    }

    #[Test]
    public function it_only_creates_clips_published_after_an_existing_clip()
    {
        /** @var AudioSource $subscription */
        $subscription = AudioSource::factory()->create();
        $subscriber = Feed::factory()->create([
            'subscription_id' => $subscription->id,
            'subscribed_at'   => CarbonImmutable::now()->subYear(),
        ]);

        // The subscriber's newest clip is from ten days ago, which is where the cursor should start.
        /** @var AudioClip $existing */
        $existing = AudioClip::factory()->create([
            'audio_source_id' => $subscription->id,
            'published_at'    => CarbonImmutable::now()->subDays(10)->roundSeconds(),
        ]);
        $subscriber->audioClips()->attach($existing, [
            'published_at' => $existing->published_at,
        ]);

        Bus::fake();

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

        $this->assertDatabaseMissing('audio_clips', ['platform_url' => $olderUrl]);
        $this->assertDatabaseHas('audio_clips', ['platform_url' => $newerUrl]);
        $this->assertEquals(2, $subscriber->audioClips()->count());
    }

    #[Test]
    public function it_attaches_new_clips_to_every_subscriber()
    {
        /** @var AudioSource $subscription */
        $subscription = AudioSource::factory()->create();

        $subscribers = Feed::factory()->count(2)->create([
            'subscription_id' => $subscription->id,
            'subscribed_at'   => CarbonImmutable::now()->subYear(),
        ]);

        Bus::fake();

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

        $this->assertDatabaseCount('audio_clips', 0);
    }

    #[Test]
    public function it_rejects_an_explicit_subscriber_that_is_not_subscribed()
    {
        /** @var AudioSource $subscription */
        $subscription = AudioSource::factory()->create();

        // A real subscriber, so the job gets past the no-subscribers check.
        Feed::factory()->create([
            'subscription_id' => $subscription->id,
            'subscribed_at'   => CarbonImmutable::now()->subYear(),
        ]);

        // A feed that is not subscribed to this source.
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
