<?php

namespace Tests\Http\Controllers;

use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Platforms\Contracts\Chapter;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShowChaptersTest extends TestCase
{
    /**
     * @param  list<Chapter>  $chapters
     */
    private function clipInFeed(Feed $feed, array $chapters): AudioClip
    {
        /** @var AudioClip $clip */
        $clip = AudioClip::factory()->create([
            'audio_source_id' => AudioSource::factory()->create()->id,
            'chapters'        => $chapters,
        ]);

        $feed->audioClips()->attach($clip, ['published_at' => CarbonImmutable::now()]);

        return $clip;
    }

    #[Test]
    public function it_shows_a_clips_chapters_in_the_podcasting_2_0_format()
    {
        $feed = Feed::factory()->create();
        $clip = $this->clipInFeed($feed, [new Chapter(0, 'Intro'), new Chapter(175, 'Main')]);

        $this->get("rss/{$feed->uuid}/clips/{$clip->id}/chapters.json")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json+chapters')
            ->assertExactJson([
                'version'  => '1.2.0',
                'chapters' => [
                    ['startTime' => 0, 'title' => 'Intro'],
                    ['startTime' => 175, 'title' => 'Main'],
                ],
            ]);
    }

    #[Test]
    public function it_returns_404_for_a_clip_without_chapters()
    {
        $feed = Feed::factory()->create();
        $clip = $this->clipInFeed($feed, []);

        $this->withExceptionHandling()
            ->get("rss/{$feed->uuid}/clips/{$clip->id}/chapters.json")
            ->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_a_clip_in_another_feed()
    {
        $clip = $this->clipInFeed(Feed::factory()->create(), [new Chapter(0, 'Intro')]);

        $this->withExceptionHandling()
            ->get('rss/'.Feed::factory()->create()->uuid."/clips/{$clip->id}/chapters.json")
            ->assertNotFound();
    }
}
