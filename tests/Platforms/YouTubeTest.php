<?php

namespace Tests\Platforms;

use App\Apis\YouTubeData\ChannelMetadata;
use App\Apis\YouTubeData\VideoMetadata;
use App\Platforms\YouTube;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesYouTubeData;
use Tests\TestCase;

class YouTubeTest extends TestCase
{
    use FakesYouTubeData;

    #[Test]
    public function it_gets_clip_metadata()
    {
        $videoUrl = 'https://youtube.com/watch?v='.($videoId = 'wlijflwijf');
        $channelUrl = 'https://youtube.com/channel/'.($channelId = 'jljrelirjelg');

        $this->fakeYouTubeData(videoMetadata: new VideoMetadata(
            id: $videoId,
            title: $videoTitle = 'some video',
            description: $videoDescription = 'some description',
            channel: new ChannelMetadata(
                id: $channelId,
                name: $channelName = 'some channel',
            ),
        ));

        /** @var YouTube $youtube */
        $youtube = $this->app->make(YouTube::class);

        $metadata = $youtube->getClipMetadata($videoUrl);

        $this->assertEquals($metadata->title, $videoTitle);
        $this->assertEquals($metadata->description, $videoDescription);
        $this->assertEquals($metadata->canonicalUrl, $videoUrl);
        $this->assertEquals($metadata->source->canonicalUrl, $channelUrl);
        $this->assertEquals($metadata->source->name, $channelName);
    }

    #[Test]
    public function it_gets_source_metadata()
    {
        $url = 'https://youtube.com/channel/'.($id = 'lwjflwjfwljfw');

        $this->fakeYouTubeData(channelMetadata: new ChannelMetadata(
            id: $id,
            name: $name = 'some channel',
        ));

        /** @var YouTube $youtube */
        $youtube = $this->app->make(YouTube::class);

        $metadata = $youtube->getSourceMetadata($url);

        $this->assertEquals($name, $metadata->name);
        $this->assertEquals($url, $metadata->canonicalUrl);
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

    #[Test]
    public function it_gets_clip_urls()
    {
        $url1 = 'https://youtube.com/watch?v='.($videoId1 = 'leirjieljrg');
        $url2 = 'https://youtube.com/watch?v='.($videoId2 = 'wlefjlwifjw');
        $url3 = 'https://youtube.com/watch?v='.($videoId3 = 'ergeligjleg');

        $this->fakeYouTubeData(videoIdsForChannel: [$videoId1, $videoId2, $videoId3]);

        /** @var YouTube $youtube */
        $youtube = $this->app->make(YouTube::class);

        $this->assertEquals(
            [$url1, $url2, $url3],
            $youtube->getClipUrlsPublishedSince('https://youtube.com/channel/wlifjliwjf', now()->subDay()),
        );
    }
}
