<?php

namespace Tests\Platforms;

use App\Platforms\YouTube;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class YouTubeTest extends TestCase
{
    #[Test]
    public function it_gets_clip_metadata()
    {
        $clipUrl = 'https://youtube.com/watch?v='.($clipId = 'leirjieljrg');
        $sourceUrl = 'https://youtube.com/channel/'.($sourceId = 'eiorjg90ej');

        Http::fake(['*' => Http::response([
            'items' => [
                [
                    'id' => $clipId,
                    'snippet' => [
                        'title' => $title = 'some video',
                        'description' => $description = 'foo bar',
                        'channelId' => $sourceId,
                        'channelTitle' => $sourceName = 'some channel',
                    ],
                ],
            ],
        ])]);

        /** @var YouTube $youtube */
        $youtube = $this->app->make(YouTube::class);

        $metadata = $youtube->getClipMetadata($clipUrl);

        $this->assertEquals($metadata->title, $title);
        $this->assertEquals($metadata->description, $description);
        $this->assertEquals($metadata->canonicalUrl, $clipUrl);
        $this->assertEquals($metadata->source->canonicalUrl, $sourceUrl);
        $this->assertEquals($metadata->source->name, $sourceName);
    }

    #[Test]
    public function it_downloads_audio()
    {
        $url = 'https://youtube.com/watch?v=foo';

        $content = 'mp3 content';

        Process::fake(["'./yt-dlp' '-x' '--audio-format=mp3' '--audio-quality=2' '-o' '*' '$url'" => function (PendingProcess $process) use ($content) {
            $file = collect($process->command)->first(fn ($s) => Str::endsWith($s, '.mp3'));

            file_put_contents($file, $content);

            return Process::result();
        }]);

        /** @var YouTube $youtube */
        $youtube = $this->app->make(YouTube::class);

        $mp3 = $youtube->downloadAudio($url);

        $this->assertFileExists($mp3);
        $this->assertEquals($content, file_get_contents($mp3));
    }
}
