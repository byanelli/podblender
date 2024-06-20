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
    public function it_gets_canonical_urls()
    {
        $url = 'http://www.youtube.com/watch?v=foo&otherparam=bar';
        $canonicalUrl = 'https://youtube.com/watch?v=foo';

        Process::fake(["'./yt-dlp' '--dump-json' '*'" => Process::result(output: json_encode([
            'webpage_url' => $canonicalUrl,
        ]))]);

        /** @var SoundCloud $soundCloud */
        $soundCloud = $this->app->make(SoundCloud::class);

        $this->assertEquals($canonicalUrl, $soundCloud->getCanonicalUrl($url));
    }

    #[Test]
    public function it_gets_metadata()
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

        $metadata = $soundCloud->getMetadata($url);

        $this->assertEquals($metadata->id, $url);
        $this->assertEquals($metadata->title, $title);
        $this->assertEquals($metadata->description, $description);
        $this->assertEquals($metadata->sourceId, $uploaderUrl);
        $this->assertEquals($metadata->sourceName, $uploader);
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
