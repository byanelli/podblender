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
            publishedAt: $publishTime = now()->subDay()->roundSeconds(),
            channel: new ChannelMetadata(
                id: $channelId,
                name: $channelName = 'some channel',
            ),
            durationSeconds: 600,
        ));

        /** @var YouTube $youtube */
        $youtube = $this->app->make(YouTube::class);

        $metadata = $youtube->getClipMetadata($videoUrl);

        $this->assertEquals($videoTitle, $metadata->title);
        $this->assertEquals($videoDescription, $metadata->description);
        $this->assertEquals($videoUrl, $metadata->canonicalUrl);
        $this->assertEquals($publishTime, $metadata->publishedAt);
        $this->assertEquals($channelUrl, $metadata->source->canonicalUrl);
        $this->assertEquals($channelName, $metadata->source->name);
    }

    #[Test]
    public function it_estimates_download_time_from_video_duration()
    {
        $videoUrl = 'https://youtube.com/watch?v='.($videoId = 'wlijflwijf');

        // 600s of ~160 kbps audio = 12 MB; at a pessimistic 2 Mbps (250 KB/s)
        // that's 48s, plus fixed overhead.
        $this->fakeYouTubeData(videoMetadata: new VideoMetadata(
            id: $videoId,
            title: 'some video',
            description: 'some description',
            publishedAt: now(),
            channel: new ChannelMetadata(id: 'channel-id', name: 'some channel'),
            durationSeconds: 600,
        ));

        /** @var YouTube $youtube */
        $youtube = $this->app->make(YouTube::class);

        $this->assertEquals(108, $youtube->getClipMetadata($videoUrl)->estimatedDownloadTime);
    }

    #[Test]
    public function it_reports_no_estimate_when_the_video_has_no_duration()
    {
        $videoUrl = 'https://youtube.com/watch?v='.($videoId = 'wlijflwijf');

        $this->fakeYouTubeData(videoMetadata: new VideoMetadata(
            id: $videoId,
            title: 'some video',
            description: 'some description',
            publishedAt: now(),
            channel: new ChannelMetadata(id: 'channel-id', name: 'some channel'),
            durationSeconds: null,
        ));

        /** @var YouTube $youtube */
        $youtube = $this->app->make(YouTube::class);

        $this->assertNull($youtube->getClipMetadata($videoUrl)->estimatedDownloadTime);
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

        // Matches the download yt-dlp runs when it goes straight to YouTube, rather than by way of a proxy.
        Process::fake(["*'--extract-audio' '*' '--audio-format=mp3' '--audio-quality=2' '-o' '*' '$url'" => function (PendingProcess $process) use ($content) {
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
