<?php

namespace Tests\Platforms;

use App\Platforms\Twitch;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TwitchTest extends TestCase
{
    #[Test]
    public function it_gets_clip_metadata_for_twitch_vod()
    {
        $clipUrl = 'https://twitch.tv/videos/'.($id = '12345');
        $sourceUrl = 'https://twitch.tv/'.($sourceId = 'somechannel');

        Process::fake([Process::result(output: json_encode([
            'extractor' => 'twitch:vod',
            'webpage_url_basename' => $id,
            'title' => $title = 'some video',
            'timestamp' => ($publishedAt = now()->subDay()->roundSeconds())->unix(),
            'uploader' => $sourceName = 'SomeChannel',
            'uploader_id' => $sourceId,
        ]))]);

        /** @var Twitch $twitch */
        $twitch = $this->app->make(Twitch::class);

        $metadata = $twitch->getClipMetadata($clipUrl);

        $this->assertEquals($title, $metadata->title);
        $this->assertEquals('', $metadata->description);
        $this->assertEquals($clipUrl, $metadata->canonicalUrl);
        $this->assertEquals($publishedAt, $metadata->publishedAt);
        $this->assertEquals($sourceName, $metadata->source->name);
        $this->assertEquals($sourceUrl, $metadata->source->canonicalUrl);
    }

    #[Test]
    public function it_gets_clip_metadata_for_twitch_clips()
    {
        $clipUrl = 'https://twitch.tv/somechannel/clip/'.($id = '12345');
        $sourceUrl = 'https://twitch.tv/'.$sourceId = 'somechannel';

        Process::fake([Process::result(output: json_encode([
            'extractor' => 'twitch:clips',
            'webpage_url_basename' => $id,
            'title' => $title = 'some video',
            'timestamp' => ($publishedAt = now()->subDay()->roundSeconds())->unix(),
            'uploader' => $sourceName = 'SomeChannel',
            'uploader_id' => $sourceId,
        ]))]);

        /** @var Twitch $twitch */
        $twitch = $this->app->make(Twitch::class);

        $metadata = $twitch->getClipMetadata($clipUrl);

        $this->assertEquals($title, $metadata->title);
        $this->assertEquals('', $metadata->description);
        $this->assertEquals($clipUrl, $metadata->canonicalUrl);
        $this->assertEquals($publishedAt, $metadata->publishedAt);
        $this->assertEquals($sourceName, $metadata->source->name);
        $this->assertEquals($sourceUrl, $metadata->source->canonicalUrl);
    }

    #[Test]
    public function it_gets_source_metadata()
    {
        $name = 'ThePrimeagen';
        $url = 'https://twitch.tv/ThePrimeagen';

        /** @var Twitch $twitch */
        $twitch = $this->app->make(Twitch::class);

        $metadata = $twitch->getSourceMetadata($url);

        $this->assertEquals($name, $metadata->name);
        $this->assertEquals($url, $metadata->canonicalUrl);
    }

    #[Test]
    public function it_downloads_audio()
    {
        $url = 'https://twitch.tv/videos/12345';

        $content = 'mp3 content';

        Process::fake(["'./yt-dlp' '*' '--extract-audio' '--no-check-certificates' '*' '--audio-format=mp3' '--audio-quality=2' '-o' '*' '$url'" => function (PendingProcess $process) use ($content) {
            $file = collect($process->command)->first(fn ($s) => Str::endsWith($s, '.mp3'));

            file_put_contents($file, $content);

            return Process::result();
        }]);

        /** @var Twitch $twitch */
        $twitch = $this->app->make(Twitch::class);

        $mp3 = $twitch->downloadAudio($url);

        $this->assertFileExists($mp3);
        $this->assertEquals($content, file_get_contents($mp3));
    }
}
