<?php

namespace Tests\Http\Controllers;

use App\Http\Routes;
use App\Http\Routes\Web;
use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Models\User;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShowRssTest extends TestCase
{
    #[Test]
    public function it_shows_the_feed() {
        /** @var Feed $feed */
        $feed = Feed::factory()->create([Feed::COL_USER_ID => User::factory()->create()->id]);
        /** @var AudioSource $source */
        $source = AudioSource::factory()->create();

        /** @var AudioClip $clip */
        $feed->audioClips()->attach($clip = AudioClip::factory()->create([
            AudioClip::COL_AUDIO_SOURCE_ID => $source->id,
        ]));

        $response = $this->get("rss/{$feed->id}")->content();

        $h = fn($s) => htmlentities($s);

        $this->assertStringContainsString("<title>{$h($feed->name)}</title>", $response);
        $this->assertStringContainsString("<link>".Web::rss($feed)."</link>", $response);
        $this->assertStringContainsString("<itunes:email>{$feed->user->email}</itunes:email>", $response);
        $this->assertStringContainsString("<itunes:author>{$h($feed->user->name)}</itunes:author>", $response);
        $this->assertStringContainsString("<title>{$h($clip->title)}</title>", $response);
        $this->assertStringContainsString("<link>$clip->source_url</link>", $response);
        $this->assertStringContainsString("<description>{$h($clip->description)}</description>", $response);
        $this->assertStringContainsString("<pubDate>{$clip->created_at->format(DateTimeInterface::RSS)}</pubDate>", $response);
        $this->assertStringContainsString("<itunes:duration>$clip->formatted_time</itunes:duration>", $response);
        $this->assertStringContainsString("<enclosure url=\"$clip->audio_url", $response);
        $this->assertStringContainsString("<guid isPermaLink=\"false\">$clip->guid</guid>", $response);
    }
}
