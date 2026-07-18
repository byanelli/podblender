<?php

namespace Tests\Jobs;

use App\Jobs\UpdateSubscription;
use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\SourceMetadata;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesDispatcher;
use Tests\Concerns\FakesPlatform;
use Tests\TestCase;

class UpdateSubscriptionTest extends TestCase
{
    use FakesDispatcher, FakesPlatform;

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
}
