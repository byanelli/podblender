<?php

namespace Tests\Http\Controllers;

use App\Enums\AudioSourceType;
use App\Enums\ClipProcessingState;
use App\Models\AudioClip;
use App\Models\AudioClipFeed;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShowRssTest extends TestCase
{
    #[Test]
    public function it_shows_the_feed()
    {
        /** @var Feed $feed */
        $feed = Feed::factory()->create([Feed::COL_USER_ID => User::factory()->create()->id])
            ->load(Feed::REL_USER);

        /** @var AudioSource $source */
        $source = AudioSource::factory()->create();

        /** @var AudioClip $clip */
        $clip = AudioClip::factory()->create([
            AudioClip::COL_AUDIO_SOURCE_ID => $source->id,
            AudioClip::COL_PROCESSING_STATE => ClipProcessingState::Processed,
        ])
            ->load(AudioClip::REL_AUDIO_SOURCE);

        // The date a feed presents a clip at lives on the pivot, and is deliberately not the clip's own publication
        // date here: the two differ for any clip added to a feed by hand, and this feed should report its own.
        $feed->audioClips()->attach($clip, [
            AudioClipFeed::COL_PUBLISHED_AT => $publishedAt = CarbonImmutable::parse('2025-03-04 05:06:07'),
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
        $feed = Feed::factory()->create([Feed::COL_USER_ID => User::factory()->create()->id])
            ->load(Feed::REL_USER);

        /** @var AudioSource $source */
        $source = AudioSource::factory()->create();

        /** @var AudioClip $clip */
        $clip = AudioClip::factory()->create([
            AudioClip::COL_AUDIO_SOURCE_ID => $source->id,
            AudioClip::COL_PROCESSING_STATE => ClipProcessingState::Processed,
        ]);

        $feed->audioClips()->attach($clip, [
            AudioClipFeed::COL_PUBLISHED_AT => CarbonImmutable::parse('2025-03-04 05:06:07'),
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

    /**
     * A feed subscribed to $source, holding one processed clip published by
     * $clipSource (which for a playlist need not be the source subscribed to).
     */
    private function subscribedFeed(AudioSource $source, ?AudioSource $clipSource = null): Feed
    {
        /** @var Feed $feed */
        $feed = Feed::factory()->create([
            Feed::COL_USER_ID => User::factory()->create()->id,
            Feed::COL_SUBSCRIPTION_ID => $source->id,
        ]);

        /** @var AudioClip $clip */
        $clip = AudioClip::factory()->create([
            AudioClip::COL_AUDIO_SOURCE_ID => ($clipSource ?? $source)->id,
            AudioClip::COL_PROCESSING_STATE => ClipProcessingState::Processed,
        ]);

        $feed->audioClips()->attach($clip, [
            AudioClipFeed::COL_PUBLISHED_AT => CarbonImmutable::parse('2025-03-04 05:06:07'),
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
            AudioSource::COL_NAME => 'Lecture Channel',
            AudioSource::COL_TYPE => AudioSourceType::Channel,
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
            AudioSource::COL_NAME => 'Select Lectures',
            AudioSource::COL_TYPE => AudioSourceType::Playlist,
            AudioSource::COL_AUTHOR_NAME => 'Lecture Channel',
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
            AudioSource::COL_NAME => 'Select Lectures',
            AudioSource::COL_TYPE => AudioSourceType::Playlist,
            AudioSource::COL_AUTHOR_NAME => 'Lecture Channel',
        ]);

        /** @var AudioSource $uploader */
        $uploader = AudioSource::factory()->create([AudioSource::COL_NAME => 'A Guest Speaker']);

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
        $feed = Feed::factory()->create([Feed::COL_USER_ID => User::factory()->create()->id]);

        /** @var AudioSource $source */
        $source = AudioSource::factory()->create();

        // Create the clips in the opposite order to how they should come out, so that emitting them in insert order
        // (the bug) would fail this test.
        /** @var AudioClip $older */
        $older = AudioClip::factory()->create([
            AudioClip::COL_AUDIO_SOURCE_ID => $source->id,
            AudioClip::COL_TITLE => $olderTitle = 'The older episode',
            AudioClip::COL_PROCESSING_STATE => ClipProcessingState::Processed,
        ]);

        /** @var AudioClip $newer */
        $newer = AudioClip::factory()->create([
            AudioClip::COL_AUDIO_SOURCE_ID => $source->id,
            AudioClip::COL_TITLE => $newerTitle = 'The newer episode',
            AudioClip::COL_PROCESSING_STATE => ClipProcessingState::Processed,
        ]);

        // The pivot date, not the clip's own publication date, is what the feed orders by.
        $feed->audioClips()->attach($older, [
            AudioClipFeed::COL_PUBLISHED_AT => CarbonImmutable::parse('2025-01-01 00:00:00'),
        ]);
        $feed->audioClips()->attach($newer, [
            AudioClipFeed::COL_PUBLISHED_AT => CarbonImmutable::parse('2025-06-01 00:00:00'),
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
