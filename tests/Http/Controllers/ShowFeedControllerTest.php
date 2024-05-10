<?php

namespace Tests\Http\Controllers;

use App\Http\Urls;
use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Models\Feed;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShowFeedControllerTest extends TestCase
{
    #[Test]
    public function it_shows_the_feed() {
        /** @var Feed $feed */
        $feed = Feed::factory()->create();
        /** @var AudioSource $source */
        $source = AudioSource::factory()->create();

        /** @var AudioClip $clip */
        $feed->audioClips()->attach($clip = AudioClip::factory()->create([
            AudioClip::COL_AUDIO_SOURCE_ID => $source->id,
        ]));

        $urls = $this->app->make(Urls::class);

        $response = $this->get("feeds/{$feed->id}")->content();

        $formattedDescription = htmlentities($clip->description);

        $this->assertStringContainsString("<title>$feed->name</title>", $response);
        $this->assertStringContainsString("<link>{$urls->showFeed($feed)}</link>", $response);
        $this->assertStringContainsString("<itunes:email>$feed->authorEmail</itunes:email>", $response);
        $this->assertStringContainsString("<itunes:author>$feed->authorName</itunes:author>", $response);
        $this->assertStringContainsString("<title>$clip->title</title>", $response);
        $this->assertStringContainsString("<link>$clip->source_url</link>", $response);
        $this->assertStringContainsString("<description>$formattedDescription</description>", $response);
        $this->assertStringContainsString("<pubDate>{$clip->created_at->format(DateTimeInterface::RSS)}</pubDate>", $response);
        $this->assertStringContainsString("<itunes:duration>$clip->formatted_time</itunes:duration>", $response);
        $this->assertStringContainsString("<enclosure url=\"$clip->audio_url", $response);
        $this->assertStringContainsString("<guid isPermaLink=\"false\">$clip->guid</guid>", $response);
    }
}
