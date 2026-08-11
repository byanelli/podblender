<?php

namespace Tests\Http\Controllers;

use App\Enums\AudioSourceType;
use App\Enums\ClipProcessingState;
use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShowRssTest extends TestCase
{
    #[Test]
    public function it_shows_the_feed()
    {
        /** @var Feed $feed */
        $feed = Feed::factory()->create(['user_id' => User::factory()->create()->id])
            ->load('user');

        /** @var AudioSource $source */
        $source = AudioSource::factory()->create();

        /** @var AudioClip $clip */
        $clip = AudioClip::factory()->create([
            'audio_source_id' => $source->id,
            'processing_state' => ClipProcessingState::Processed,
        ])
            ->load('audioSource');

        // The date a feed presents a clip at lives on the pivot, and is deliberately not the clip's own publication
        // date here: the two differ for any clip added to a feed by hand, and this feed should report its own.
        $feed->audioClips()->attach($clip, [
            'published_at' => $publishedAt = CarbonImmutable::parse('2025-03-04 05:06:07'),
        ]);

        $response = $this->get("rss/{$feed->uuid}")->content();

        $h = fn ($s) => htmlentities($s);

        $this->assertStringContainsString("<title>{$h($feed->name)}</title>", $response);
        $this->assertStringContainsString('<link>'.url("rss/{$feed->uuid}").'</link>', $response);
        $this->assertStringContainsString("<description>{$feed->description}</description>", $response);
        $this->assertStringContainsString("<itunes:email>{$feed->user->email}</itunes:email>", $response);
        // A hand-built feed has no publisher of its own, so it's credited to its owner.
        $this->assertStringContainsString("<itunes:author>{$h($feed->user->name)}</itunes:author>", $response);
        $this->assertStringContainsString("<title>{$h($clip->title)}</title>", $response);
        $this->assertStringContainsString("<link>{$clip->platform_url}</link>", $response);
        $this->assertStringContainsString("<description>{$h($clip->description)}</description>", $response);
        $this->assertStringContainsString("<pubDate>{$publishedAt->format(DateTimeInterface::RSS)}</pubDate>", $response);
        $this->assertStringNotContainsString($clip->published_at->format(DateTimeInterface::RSS), $response);
        $this->assertStringContainsString("<itunes:duration>$clip->formatted_time</itunes:duration>", $response);
        $this->assertStringContainsString("<enclosure url=\"$clip->audio_url", $response);
        $this->assertStringContainsString("<guid isPermaLink=\"false\">$clip->guid</guid>", $response);
    }

    #[Test]
    public function its_xml_is_parseable()
    {
        /** @var Feed $feed */
        $feed = Feed::factory()
            ->create(['user_id' => User::factory()->create()->id])
            ->load('user');

        /** @var AudioSource $source */
        $source = AudioSource::factory()->create();

        /** @var AudioClip $clip */
        $clip = AudioClip::factory()->create([
            'audio_source_id' => $source->id,
            'processing_state' => ClipProcessingState::Processed,
        ]);

        $feed->audioClips()->attach($clip, [
            'published_at' => CarbonImmutable::parse('2025-03-04 05:06:07'),
        ]);

        $body = $this->get("rss/{$feed->uuid}")->content();

        // A podcast app has to be able to parse the feed, so it must be valid
        // XML — a feed that's not (e.g. a literal "\n" corrupting the prolog)
        // is rejected by every subscription client.
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->assertNotFalse($xml, "Feed XML is not parseable:\n{$body}");
        $this->assertSame([], $errors, 'Feed XML has parse errors.');
    }

    #[Test]
    public function enclosure_urls_follow_the_request_host_not_app_url()
    {
        // A feed requested through the ngrok tunnel must point its episode
        // audio at the tunnel host, not at APP_URL (the local machine).
        // Enclosure URLs are rooted per-request by the public disk's relative
        // url + url().
        /** @var Feed $feed */
        $feed = Feed::factory()->create(['user_id' => User::factory()->create()->id])
            ->load('user');

        /** @var AudioSource $source */
        $source = AudioSource::factory()->create();

        /** @var AudioClip $clip */
        $clip = AudioClip::factory()->create([
            'audio_source_id' => $source->id,
            'processing_state' => ClipProcessingState::Processed,
        ]);

        $feed->audioClips()->attach($clip);
        $feed->load('audioClipsFinishedProcessing');

        // Render as if the request came in through a public tunnel, so the
        // request root (which url() absolutizes against) is the tunnel host.
        $request = Request::create("https://tunnel.example.test/rss/{$feed->uuid}", 'GET', [], [], [], [
            'HTTPS' => 'on',
            'HTTP_HOST' => 'tunnel.example.test',
        ]);
        $this->app->instance('request', $request);
        $this->app['url']->setRequest($request);

        $body = view('rss', compact('feed'))->render();

        $this->assertStringContainsString('<enclosure url="https://tunnel.example.test/storage/', $body);
        $this->assertStringNotContainsString('localhost', $body);
    }

    /**
     * A feed subscribed to $source, holding one processed clip published by
     * $clipSource (which for a playlist need not be the source subscribed to).
     */
    private function subscribedFeed(AudioSource $source, ?AudioSource $clipSource = null): Feed
    {
        /** @var Feed $feed */
        $feed = Feed::factory()->create([
            'user_id' => User::factory()->create()->id,
            'subscription_id' => $source->id,
        ]);

        /** @var AudioClip $clip */
        $clip = AudioClip::factory()->create([
            'audio_source_id' => ($clipSource ?? $source)->id,
            'processing_state' => ClipProcessingState::Processed,
        ]);

        $feed->audioClips()->attach($clip, [
            'published_at' => CarbonImmutable::parse('2025-03-04 05:06:07'),
        ]);

        return $feed;
    }

    #[Test]
    public function it_credits_a_subscribed_feed_to_the_channel_rather_than_the_user()
    {
        // The podcast is published by the channel; the podblender user merely
        // set the feed up, and a listener seeing their name would be confused.
        /** @var AudioSource $channel */
        $channel = AudioSource::factory()->create([
            'name' => 'Lecture Channel',
            'type' => AudioSourceType::Channel,
        ]);

        $feed = $this->subscribedFeed($channel);

        $response = $this->get("rss/{$feed->uuid}")->content();

        $this->assertStringContainsString('<itunes:author>Lecture Channel</itunes:author>', $response);
        $this->assertStringNotContainsString(htmlentities($feed->user->name), $response);
    }

    #[Test]
    public function it_credits_a_playlist_feed_to_the_channel_that_owns_it()
    {
        // A playlist's name describes its contents, not a person, so crediting
        // "Select Lectures" as the author would read as nonsense.
        /** @var AudioSource $playlist */
        $playlist = AudioSource::factory()->create([
            'name' => 'Select Lectures',
            'type' => AudioSourceType::Playlist,
            'author_name' => 'Lecture Channel',
        ]);

        $feed = $this->subscribedFeed($playlist);

        $response = $this->get("rss/{$feed->uuid}")->content();

        $this->assertStringContainsString('<itunes:author>Lecture Channel</itunes:author>', $response);
    }

    #[Test]
    public function it_credits_each_episode_to_the_channel_that_uploaded_it()
    {
        /** @var AudioSource $playlist */
        $playlist = AudioSource::factory()->create([
            'name' => 'Select Lectures',
            'type' => AudioSourceType::Playlist,
            'author_name' => 'Lecture Channel',
        ]);

        /** @var AudioSource $uploader */
        $uploader = AudioSource::factory()->create(['name' => 'A Guest Speaker']);

        $feed = $this->subscribedFeed($playlist, clipSource: $uploader);

        $response = $this->get("rss/{$feed->uuid}")->content();

        // The channel is credited to the playlist's owner, the episode to
        // whoever actually uploaded it.
        $this->assertStringContainsString('<itunes:author>Lecture Channel</itunes:author>', $response);
        $this->assertStringContainsString('<itunes:author>A Guest Speaker</itunes:author>', $response);
    }

    #[Test]
    public function it_orders_items_by_the_date_the_feed_presents_them_newest_first()
    {
        /** @var Feed $feed */
        $feed = Feed::factory()->create(['user_id' => User::factory()->create()->id]);

        /** @var AudioSource $source */
        $source = AudioSource::factory()->create();

        // Create the clips in the opposite order to how they should come out, so that emitting them in insert order
        // (the bug) would fail this test.
        /** @var AudioClip $older */
        $older = AudioClip::factory()->create([
            'audio_source_id' => $source->id,
            'title' => $olderTitle = 'The older episode',
            'processing_state' => ClipProcessingState::Processed,
        ]);

        /** @var AudioClip $newer */
        $newer = AudioClip::factory()->create([
            'audio_source_id' => $source->id,
            'title' => $newerTitle = 'The newer episode',
            'processing_state' => ClipProcessingState::Processed,
        ]);

        // The pivot date, not the clip's own publication date, is what the feed orders by.
        $feed->audioClips()->attach($older, [
            'published_at' => CarbonImmutable::parse('2025-01-01 00:00:00'),
        ]);
        $feed->audioClips()->attach($newer, [
            'published_at' => CarbonImmutable::parse('2025-06-01 00:00:00'),
        ]);

        $response = $this->get("rss/{$feed->uuid}")->content();

        $this->assertStringContainsString($newerTitle, $response);
        $this->assertStringContainsString($olderTitle, $response);

        // Newest first: the newer episode's title appears before the older one's in the RSS body.
        $this->assertLessThan(
            strpos($response, $olderTitle),
            strpos($response, $newerTitle),
            'RSS items are not ordered newest-first by the pivot published_at.'
        );
    }
}
