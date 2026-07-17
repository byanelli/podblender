<?php

namespace Tests\Http\Controllers;

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
}
