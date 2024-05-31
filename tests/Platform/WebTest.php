<?php

namespace Tests\Platform;

use App\Platforms\Web;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesWhisper;
use Tests\TestCase;

class WebTest extends TestCase
{
    use FakesWhisper;

    #[Test]
    public function it_gets_metadata() {
        Http::fake(['*' => Http::response(body: json_encode([
            [
                'title' => $title = 'Kitten Thinks Of Nothing But Murder All Day',
                'publisher' => $publisher = 'The Onion',
                'author' => ['foo', 'bar'],
                'text' => 'zzz',
            ]
        ]))]);

        /** @var Web $web */
        $web = $this->app->make(Web::class);

        $metadata = $web->getMetadata($url = 'https://www.theonion.com/kitten-thinks-of-nothing-but-murder-all-day-1819588260');

        $this->assertEquals($url, $metadata->id);
        $this->assertEquals($title, $metadata->title);
        $this->assertEquals('Article by foo and bar', $metadata->description);
        $this->assertEquals('theonion.com', $metadata->sourceId);
        $this->assertEquals($publisher, $metadata->sourceName);
    }

    #[Test]
    public function it_downloads_audio() {
        Http::fake(['*' => Http::response(body: json_encode([
            [
                'title' => 'Kitten Thinks Of Nothing But Murder All Day',
                'publisher' => 'The Onion',
                'author' => ['foo', 'bar'],
                'text' => $text = 'zzz',
            ]
        ]))]);

        $this->fakeWhisper();

        /** @var Web $web */
        $web = $this->app->make(Web::class);

        $mp3 = $web->downloadAudio('https://www.theonion.com/kitten-thinks-of-nothing-but-murder-all-day-1819588260');

        $this->assertFileExists($mp3);
        $this->assertEquals($text, file_get_contents($mp3));
    }
}
