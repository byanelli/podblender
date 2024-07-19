<?php

namespace Tests\Jobs;

use App\Enums\PlatformType;
use App\Jobs\InitializeSubscriptionForSubscriber;
use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\SourceMetadata;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesDispatcher;
use Tests\Concerns\FakesPlatform;
use Tests\TestCase;

class InitializeSubscriptionForSubscriberTest extends TestCase
{
    use FakesDispatcher;
    use FakesPlatform;

    #[Test]
    public function it_initializes_subscription_for_subscriber()
    {
        $source = new SourceMetadata(
            name: $sourceName = 'Some channel',
            canonicalUrl: $sourceUrl = 'https://youtube.com/channel/lwiejlwiejf'
        );

        $this->fakePlatform(clipMetadataList: [
            new ClipMetadata(
                title: $clip1Title = 'Some title',
                description: $clip1Description = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                canonicalUrl: $clip1Url = 'https://youtube.com/watch?v=efwewfwef',
                publishedAt: $clip1PublishedAt = now()->subDay()->roundSeconds(),
                source: $source,
            ),
            new ClipMetadata(
                title: $clip2Title = 'Another title',
                description: $clip2Description = 'Loeijoeirjg rgjilg jlid ldirjg, lwijigjeg.',
                canonicalUrl: $clip2Url = 'https://youtube.com/watch?v=sjfksjkf',
                publishedAt: $clip2PublishedAt = now()->subDays(2)->roundSeconds(),
                source: $source,
            ),
            new ClipMetadata(
                title: 'Yet another title',
                description: 'Kuekruhg hlijfkbdughb erljei.',
                canonicalUrl: $clip3Url = 'https://youtube.com/watch?v=dfbdfdvd',
                publishedAt: now()->subDays(4)->roundSeconds(),
                source: $source,
            ),
        ]);

        $this->fakeNoOpDispatcher();

        $subscription = AudioSource::factory()->create([
            AudioSource::COL_NAME => $sourceName,
            AudioSource::COL_PLATFORM_URL => $sourceUrl,
            AudioSource::COL_PLATFORM_TYPE => PlatformType::YouTube,
        ]);

        $subscriber = Feed::factory()->create([
            Feed::COL_SUBSCRIBED_AT => now()->subDays(3),
        ]);

        $job = new InitializeSubscriptionForSubscriber($subscription, $subscriber);

        app()->call([$job, 'handle']);

        $this->assertDatabaseHas('audio_clips', [
            AudioClip::COL_PLATFORM_URL => $clip1Url,
            AudioClip::COL_TITLE => $clip1Title,
            AudioClip::COL_DESCRIPTION => $clip1Description,
            AudioClip::COL_PUBLISHED_AT => $clip1PublishedAt,
        ]);

        $this->assertDatabaseHas('audio_clip_feed', [
            'feed_id' => $subscriber->id,
            'audio_clip_id' => AudioClip::where(AudioClip::COL_PLATFORM_URL, $clip2Url)->first()->id,
        ]);

        $this->assertDatabaseHas('audio_clips', [
            AudioClip::COL_PLATFORM_URL => $clip2Url,
            AudioClip::COL_TITLE => $clip2Title,
            AudioClip::COL_DESCRIPTION => $clip2Description,
            AudioClip::COL_PUBLISHED_AT => $clip2PublishedAt,
        ]);

        $this->assertDatabaseHas('audio_clip_feed', [
            'feed_id' => $subscriber->id,
            'audio_clip_id' => AudioClip::where(AudioClip::COL_PLATFORM_URL, $clip1Url)->first()->id,
        ]);

        $this->assertDatabaseMissing('audio_clips', [
            AudioClip::COL_PLATFORM_URL => $clip3Url,
        ]);
    }
}
