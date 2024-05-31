<?php

namespace Tests\Platform;

use App\Platforms\YouTube;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class YouTubeTest extends TestCase
{
    #[Test]
    public function it_parses_youtube_urls() {
        /** @var YouTube $youtube */
        $youtube = $this->app->make(YouTube::class);

        foreach (Data::YOUTUBE_URLS_TO_IDS as $url => $id) {
            $this->assertEquals($id, $youtube->getIdFromUrl($url), "Failed to parse: $url");
        }
    }

    #[Test]
    public function it_generates_youtube_urls_from_id() {
        /** @var YouTube $youtube */
        $youtube = $this->app->make(YouTube::class);

        $this->assertEquals('https://www.youtube.com/watch?v=127ng7botO4', $youtube->getUrlFromId('127ng7botO4'));
    }

    #[Test]
    public function it_gets_metadata() {
        Process::fake([Process::result(output: json_encode([
            'id' => $id = 'foo',
            'title' => $title = 'some video',
            'description' => $description = 'foo bar',
            'channel_id' => $sourceId = 'eiorjg90ej',
            'channel' => $sourceName = 'some channel',
        ]))]);

        /** @var YouTube $youtube */
        $youtube = $this->app->make(YouTube::class);

        $metadata = $youtube->getMetadata('foo');

        $this->assertEquals($metadata->id, $id);
        $this->assertEquals($metadata->title, $title);
        $this->assertEquals($metadata->description, $description);
        $this->assertEquals($metadata->sourceId, $sourceId);
        $this->assertEquals($metadata->sourceName, $sourceName);
    }

    #[Test]
    public function it_downloads_audio() {
        $id = 'foo';

        $content = 'mp3 content';

        Process::fake(["./yt-dlp -x --audio-format=mp3 --audio-quality=2 -o * $id" => function (PendingProcess $process) use ($content) {
            $file = collect(explode(' ', $process->command))->first(fn($s) => Str::endsWith($s, '.mp3'));

            file_put_contents($file, $content);

            return Process::result();
        }]);

        /** @var YouTube $youtube */
        $youtube = $this->app->make(YouTube::class);

        $mp3 = $youtube->downloadAudio($id);

        $this->assertFileExists($mp3);
        $this->assertEquals($content, file_get_contents($mp3));
    }
}
