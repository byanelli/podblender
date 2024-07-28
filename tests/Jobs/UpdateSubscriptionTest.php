<?php

namespace Tests\Jobs;

use App\Jobs\UpdateSubscription;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\SourceMetadata;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesDispatcher;
use Tests\Concerns\FakesPlatform;
use Tests\TestCase;

class UpdateSubscriptionTest extends TestCase
{
    use FakesPlatform, FakesDispatcher;

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
}
