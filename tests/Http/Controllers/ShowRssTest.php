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
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
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
            'audio_source_id'  => $source->id,
            'processing_state' => ClipProcessingState::Processed,
        ])
            ->load('audioSource');

        // The pivot date differs from the clip's publication date, as it does for any clip added by hand. The feed
        // should report the pivot date.
        $feed->audioClips()->attach($clip, [
            'published_at' => $publishedAt = CarbonImmutable::parse('2025-03-04 05:06:07'),
        ]);

        $response = $this->get("rss/{$feed->uuid}")->content();

        $h = fn ($s) => htmlentities($s);

        $this->assertStringContainsString("<title>{$h($feed->name)}</title>", $response);
        $this->assertStringContainsString('<link>'.url("rss/{$feed->uuid}").'</link>', $response);
        $this->assertStringContainsString("<description>{$feed->description}</description>", $response);
        $this->assertStringContainsString("<itunes:email>{$feed->user->email}</itunes:email>", $response);
        // A feed with no subscription has no publisher, so it's credited to its user.
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
    public function it_declares_the_feed_as_rss_xml()
    {
        // A bare view is sent as text/html. The podcast specs and the feed
        // validators expect application/rss+xml.
        /** @var Feed $feed */
        $feed = Feed::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->get("rss/{$feed->uuid}")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    #[Test]
    public function it_shows_enclosure_urls_when_browser_preview_is_disabled()
    {
        // Podcast clients fetch the enclosure directly, so disabling browser
        // preview must not remove it from the feed.
        Config::set('audio-preview.enabled', false);

        /** @var Feed $feed */
        $feed = Feed::factory()->create(['user_id' => User::factory()->create()->id]);

        /** @var AudioClip $clip */
        $clip = AudioClip::factory()->create([
            'audio_source_id'  => AudioSource::factory()->create()->id,
            'processing_state' => ClipProcessingState::Processed,
        ]);

        $feed->audioClips()->attach($clip);

        $response = $this->get("rss/{$feed->uuid}")->content();

        $this->assertNotEmpty($clip->audio_url);
        $this->assertStringContainsString("<enclosure url=\"{$clip->audio_url}\"", $response);
    }

    #[Test]
    public function it_gives_an_episode_with_artwork_its_own_image()
    {
        /** @var Feed $feed */
        $feed = Feed::factory()->create(['user_id' => User::factory()->create()->id]);

        /** @var AudioClip $clip */
        $clip = AudioClip::factory()->create([
            'audio_source_id'  => AudioSource::factory()->create()->id,
            'processing_state' => ClipProcessingState::Processed,
            'thumbnail_path'   => 'some-channel-some-video-abc123.jpg',
        ]);

        $feed->audioClips()->attach($clip);

        $body = $this->get("rss/{$feed->uuid}")->content();

        $this->assertNotEmpty($clip->thumbnail_url);
        $this->assertStringContainsString("<itunes:image href=\"{$clip->thumbnail_url}\"/>", $body);

        // Still valid XML with the extra element in the item.
        $this->assertNotFalse(simplexml_load_string($body));
    }

    #[Test]
    public function it_leaves_the_image_out_for_an_episode_without_artwork()
    {
        /** @var Feed $feed */
        $feed = Feed::factory()->create(['user_id' => User::factory()->create()->id]);

        /** @var AudioClip $clip */
        $clip = AudioClip::factory()->create([
            'audio_source_id'  => AudioSource::factory()->create()->id,
            'processing_state' => ClipProcessingState::Processed,
            'thumbnail_path'   => null,
        ]);

        $feed->audioClips()->attach($clip);

        $body = $this->get("rss/{$feed->uuid}")->content();

        // Given an empty href, some clients show a broken image instead of
        // falling back to the channel's image.
        $item = Str::between($body, '<item>', '</item>');

        $this->assertStringNotContainsString('<itunes:image', $item);
    }

    #[Test]
    public function it_shows_the_cover_as_both_kinds_of_channel_artwork()
    {
        /** @var Feed $feed */
        $feed = Feed::factory()->create([
            'user_id'    => User::factory()->create()->id,
            'cover_path' => 'covers/lectures-abc123.jpg',
        ]);

        $body = $this->get("rss/{$feed->uuid}")->content();

        $this->assertNotEmpty($feed->cover_url);

        // Podcast apps read itunes:image. Plain RSS readers read <image>.
        $this->assertStringContainsString("<itunes:image href=\"{$feed->cover_url}\"/>", $body);
        $this->assertStringContainsString("<url>{$feed->cover_url}</url>", $body);
        $this->assertStringContainsString('<title>'.htmlentities($feed->name).'</title>', $body);
        $this->assertStringContainsString('<link>'.url("rss/{$feed->uuid}").'</link>', $body);

        $this->assertNotFalse(simplexml_load_string($body));
    }

    #[Test]
    public function it_leaves_both_image_tags_out_for_a_feed_without_a_cover()
    {
        /** @var Feed $feed */
        $feed = Feed::factory()->create([
            'user_id'    => User::factory()->create()->id,
            'cover_path' => null,
        ]);

        $body = $this->get("rss/{$feed->uuid}")->content();

        // As with episode artwork, an empty href is worse than no tag.
        $channel = Str::before($body, '<item>');

        $this->assertStringNotContainsString('<itunes:image', $channel);
        $this->assertStringNotContainsString('<image>', $channel);
        $this->assertStringNotContainsString('placehold.co', $body);
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
            'audio_source_id'  => $source->id,
            'processing_state' => ClipProcessingState::Processed,
        ]);

        $feed->audioClips()->attach($clip, [
            'published_at' => CarbonImmutable::parse('2025-03-04 05:06:07'),
        ]);

        $body = $this->get("rss/{$feed->uuid}")->content();

        // Subscription clients reject a feed that isn't valid XML, e.g. one with
        // a literal "\n" corrupting the prolog.
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
        // A feed requested through the ngrok tunnel must use the tunnel host in
        // its enclosure URLs, not APP_URL (the local machine). The public disk's
        // url is relative, and url() resolves it against the request.
        /** @var Feed $feed */
        $feed = Feed::factory()->create(['user_id' => User::factory()->create()->id])
            ->load('user');

        /** @var AudioSource $source */
        $source = AudioSource::factory()->create();

        /** @var AudioClip $clip */
        $clip = AudioClip::factory()->create([
            'audio_source_id'  => $source->id,
            'processing_state' => ClipProcessingState::Processed,
        ]);

        $feed->audioClips()->attach($clip);
        $feed->load('audioClipsFinishedProcessing');

        // Render as if the request came in through a public tunnel.
        $request = Request::create("https://tunnel.example.test/rss/{$feed->uuid}", 'GET', [], [], [], [
            'HTTPS'     => 'on',
            'HTTP_HOST' => 'tunnel.example.test',
        ]);
        $this->app->instance('request', $request);
        $this->app['url']->setRequest($request);

        $body = view('rss', compact('feed'))->render();

        $this->assertStringContainsString('<enclosure url="https://tunnel.example.test/storage/', $body);
        $this->assertStringNotContainsString('localhost', $body);
    }

    /**
     * A feed subscribed to $source, with one processed clip published by
     * $clipSource (which for a playlist need not be the source subscribed to).
     */
    private function subscribedFeed(AudioSource $source, ?AudioSource $clipSource = null): Feed
    {
        /** @var Feed $feed */
        $feed = Feed::factory()->create([
            'user_id'         => User::factory()->create()->id,
            'subscription_id' => $source->id,
        ]);

        /** @var AudioClip $clip */
        $clip = AudioClip::factory()->create([
            'audio_source_id'  => ($clipSource ?? $source)->id,
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
        // The channel publishes the podcast. The podblender user only set the
        // feed up, and their name would confuse a listener.
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
        // A playlist's name describes its contents, so it isn't used as the
        // author.
        /** @var AudioSource $playlist */
        $playlist = AudioSource::factory()->create([
            'name'        => 'Select Lectures',
            'type'        => AudioSourceType::Playlist,
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
            'name'        => 'Select Lectures',
            'type'        => AudioSourceType::Playlist,
            'author_name' => 'Lecture Channel',
        ]);

        /** @var AudioSource $uploader */
        $uploader = AudioSource::factory()->create(['name' => 'A Guest Speaker']);

        $feed = $this->subscribedFeed($playlist, clipSource: $uploader);

        $response = $this->get("rss/{$feed->uuid}")->content();

        // The feed is credited to the playlist's owner, the episode to its
        // uploader.
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

        // Created oldest first, so emitting the clips in insert order would fail this test.
        /** @var AudioClip $older */
        $older = AudioClip::factory()->create([
            'audio_source_id'  => $source->id,
            'title'            => $olderTitle = 'The older episode',
            'processing_state' => ClipProcessingState::Processed,
        ]);

        /** @var AudioClip $newer */
        $newer = AudioClip::factory()->create([
            'audio_source_id'  => $source->id,
            'title'            => $newerTitle = 'The newer episode',
            'processing_state' => ClipProcessingState::Processed,
        ]);

        // The feed orders by the pivot date, not the clip's publication date.
        $feed->audioClips()->attach($older, [
            'published_at' => CarbonImmutable::parse('2025-01-01 00:00:00'),
        ]);
        $feed->audioClips()->attach($newer, [
            'published_at' => CarbonImmutable::parse('2025-06-01 00:00:00'),
        ]);

        $response = $this->get("rss/{$feed->uuid}")->content();

        $this->assertStringContainsString($newerTitle, $response);
        $this->assertStringContainsString($olderTitle, $response);

        $this->assertLessThan(
            strpos($response, $olderTitle),
            strpos($response, $newerTitle),
            'RSS items are not ordered newest-first by the pivot published_at.'
        );
    }
}
