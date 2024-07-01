<?php

namespace Tests\Platforms;

use App\Platforms\SoundCloud;
use App\Platforms\YouTube;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SoundCloudTest extends TestCase
{
    #[Test]
    public function it_gets_clip_metadata()
    {
        $url = 'https://soundcloud.com/artist/track';

        Process::fake(["'./yt-dlp' '--dump-json' '$url'" => Process::result(output: json_encode([
            'webpage_url' => $url,
            'title' => $title = 'bar',
            'description' => $description = 'baz',
            'uploader_url' => $uploaderUrl = 'https://soundcloud.com/artist',
            'uploader' => $uploader = 'Artist',
        ]))]);

        /** @var SoundCloud $soundCloud */
        $soundCloud = $this->app->make(SoundCloud::class);

        $metadata = $soundCloud->getClipMetadata($url);

        $this->assertEquals($metadata->title, $title);
        $this->assertEquals($metadata->description, $description);
        $this->assertEquals($metadata->canonicalUrl, $url);
        $this->assertEquals($metadata->source->canonicalUrl, $uploaderUrl);
        $this->assertEquals($metadata->source->name, $uploader);
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
