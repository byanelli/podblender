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
    public function it_gets_canonical_url_for_vod() {
        Process::fake(['*' => Process::result(output: json_encode([
            'extractor' => 'twitch:vod',
            'webpage_url_basename' => '12345',
        ]))]);

        /** @var Twitch $twitch */
        $twitch = $this->app->make(Twitch::class);

        $url = 'http://www.twitch.tv/videos/12345?junk=queryparams#andhash';
        $canonicalUrl = 'https://twitch.tv/videos/12345';

        $this->assertEquals($canonicalUrl, $twitch->getCanonicalUrl($url));
    }

    #[Test]
    public function it_gets_canonical_url_for_clip() {
        Process::fake(['*' => Process::result(output: json_encode([
            'extractor' => 'twitch:clips',
            'webpage_url_basename' => 'MeanBanana-12345',
            'uploader' => 'SomeStreamer',
        ]))]);

        /** @var Twitch $twitch */
        $twitch = $this->app->make(Twitch::class);

        $url = 'https://www.twitch.tv/somestreamer/clip/MeanBanana-12345?junk=queryparams#andhash';
        $canonicalUrl = 'https://twitch.tv/somestreamer/clip/MeanBanana-12345';

        $this->assertEquals($canonicalUrl, $twitch->getCanonicalUrl($url));
    }

    #[Test]
    public function it_gets_metadata() {
        Process::fake([Process::result(output: json_encode([
            'id' => $id = 'foo',
            'title' => $title = 'some video',
            'uploader_id' => $uploaderId = 'eiorjg90ej',
            'uploader' => $uploader = 'some channel',
        ]))]);

        /** @var Twitch $twitch */
        $twitch = $this->app->make(Twitch::class);

        $metadata = $twitch->getMetadata('https://twitch.tv/videos/12345');

        $this->assertEquals($id, $metadata->id);
        $this->assertEquals($title, $metadata->title);
        $this->assertEquals('', $metadata->description);
        $this->assertEquals($uploaderId, $metadata->sourceId);
        $this->assertEquals($uploader, $metadata->sourceName);
    }

    #[Test]
    public function it_downloads_audio() {
        $url = 'https://twitch.tv/videos/12345';

        $content = 'mp3 content';

        Process::fake(["'./yt-dlp' '-x' '--audio-format=mp3' '--audio-quality=2' '-o' '*' '$url'" => function (PendingProcess $process) use ($content) {
            $file = collect($process->command)->first(fn($s) => Str::endsWith($s, '.mp3'));

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
