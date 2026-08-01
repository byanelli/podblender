<?php

namespace Tests\Http\Controllers;

use App\Enums\ClipProcessingState;
use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Models\User;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\SourceMetadata;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesPlatform;
use Tests\TestCase;

class AddClipToFeedTest extends TestCase
{
    use FakesPlatform;

    #[Test]
    public function it_adds_a_new_clip_to_the_feed()
    {
        $url = 'https://youtube.com/watch?v=lijwliejfwlef';

        $this->fakePlatform(
            clipMetadata: new ClipMetadata(
                title: $title = 'Some title',
                description: $description = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                canonicalUrl: $url,
                publishedAt: $publishedAt = now()->subDay()->roundSeconds(),
                source: new SourceMetadata(
                    name: $sourceName = 'Some channel',
                    canonicalUrl: $sourceUrl = 'https://youtube.com/channel/lwiejlwiejf',
                    authorName: $sourceName,
                ),
            ),
        );

        // We don't want to run the DownloadAndStore job
        Bus::fake();

        $user = User::factory()->create();
        $feed = Feed::factory()->create([Feed::COL_USER_ID => $user->id]);

        $this->assertTrue($feed->audioClips()->doesntExist());

        $this->actingAs($user)->postJson("api/feeds/$feed->id/add", ['url' => $url]);

        /** @var AudioClip $clip */
        $clip = $feed->audioClips()->first();

        $this->assertNotNull($clip);
        $this->assertEquals($url, $clip->platform_url);
        $this->assertEquals($title, $clip->title);
        $this->assertEquals($description, $clip->description);
        $this->assertEquals($publishedAt, $clip->published_at);
        $this->assertEquals($sourceUrl, $clip->audioSource->platform_url);
        $this->assertEquals($sourceName, $clip->audioSource->name);
        $this->assertEquals(0, $clip->duration);
    }

    #[Test]
    public function it_attaches_an_existing_clip_to_the_feed()
    {
        $this->fakePlatform(
            clipMetadata: new ClipMetadata(
                title: 'Some title',
                description: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                canonicalUrl: $url = 'https://youtube.com/watch?v=lijwliejfwlef',
                publishedAt: now()->subDay()->roundSeconds(),
                source: new SourceMetadata(
                    name: 'Some channel',
                    canonicalUrl: 'https://youtube.com/channel/lwiejlwiejf',
                    authorName: 'Some channel',
                ),
            ),
        );

        $user = User::factory()->create();
        $feed = Feed::factory()->create([Feed::COL_USER_ID => $user->id]);
        $clip = AudioClip::factory()->create([
            AudioClip::COL_PLATFORM_URL => $url,
            AudioClip::COL_AUDIO_SOURCE_ID => AudioSource::factory()->create()->id,
        ]);

        $this->assertTrue($feed->audioClips()->doesntExist());

        $this->actingAs($user)->postJson("api/feeds/$feed->id/add", ['url' => $url]);

        $this->assertEquals(1, AudioClip::count());
        $this->assertTrue($feed->audioClips()->first()->is($clip));
    }

    #[Test]
    public function it_presents_a_clip_added_by_hand_as_published_now()
    {
        $this->travelTo($now = CarbonImmutable::parse('2026-01-02 03:04:05'));

        // A talk from years ago, of the sort someone finds and adds to a feed by hand.
        $publishedAt = CarbonImmutable::parse('2023-05-06 07:08:09');

        $this->fakePlatform(
            clipMetadata: new ClipMetadata(
                title: 'An old talk',
                description: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                canonicalUrl: $url = 'https://youtube.com/watch?v=lijwliejfwlef',
                publishedAt: $publishedAt,
                source: new SourceMetadata(
                    name: 'Some channel',
                    canonicalUrl: 'https://youtube.com/channel/lwiejlwiejf',
                    authorName: 'Some channel',
                ),
            ),
        );

        Bus::fake();

        $user = User::factory()->create();
        $feed = Feed::factory()->create([Feed::COL_USER_ID => $user->id]);

        $this->actingAs($user)->postJson("api/feeds/$feed->id/add", ['url' => $url]);

        /** @var AudioClip $clip */
        $clip = $feed->audioClips()->first();

        // The clip keeps the date the platform published it...
        $this->assertEquals($publishedAt, $clip->published_at);

        // ...but this feed presents it as new, so that it arrives at the top of a podcast app rather than three years
        // down the listing where nobody would ever come across it.
        $this->assertEquals($now, $clip->pivot->published_at);
    }

    #[Test]
    public function it_adds_the_same_clip_url_only_once()
    {
        $this->fakePlatform(
            clipMetadata: new ClipMetadata(
                title: 'Some title',
                description: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                canonicalUrl: $url = 'https://youtube.com/watch?v=lijwliejfwlef',
                publishedAt: now()->subDay()->roundSeconds(),
                source: new SourceMetadata(
                    name: 'Some channel',
                    canonicalUrl: 'https://youtube.com/channel/lwiejlwiejf',
                    authorName: 'Some channel',
                ),
            ),
        );

        Bus::fake();

        $user = User::factory()->create();
        $feed = Feed::factory()->create([Feed::COL_USER_ID => $user->id]);
        $clip = AudioClip::factory()->create([
            AudioClip::COL_PLATFORM_URL => $url,
            AudioClip::COL_AUDIO_SOURCE_ID => AudioSource::factory()->create()->id,
            AudioClip::COL_PROCESSING_STATE => ClipProcessingState::Processed,
        ]);

        // Add the same clip to the same feed twice, as an impatient user double-submitting the form would.
        $this->actingAs($user)->postJson("api/feeds/$feed->id/add", ['url' => $url]);
        $this->actingAs($user)->postJson("api/feeds/$feed->id/add", ['url' => $url]);

        // A single pivot row: the second add is a no-op, not a duplicate.
        $this->assertEquals(1, $feed->audioClips()->count());
        $this->assertDatabaseCount('audio_clip_feed', 1);

        // And so a single item in the RSS.
        $response = $this->get("rss/{$feed->uuid}")->content();
        $this->assertEquals(1, substr_count($response, "<guid isPermaLink=\"false\">{$clip->guid}</guid>"));
    }

    #[Test]
    public function it_does_not_add_a_clip_to_another_users_feed()
    {
        $this->expectException(AuthorizationException::class);

        $user = User::factory()->create();
        $feed = Feed::factory()->create([Feed::COL_USER_ID => $user->id + 1]);
        $clip = AudioClip::factory()->create([
            AudioClip::COL_AUDIO_SOURCE_ID => AudioSource::factory()->create()->id,
        ]);

        $this->actingAs($user)->postJson("api/feeds/$feed->id/add", ['url' => $clip->platform_url]);
    }
}
