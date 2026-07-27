<?php

namespace Tests\Platforms;

use App\Apis\YouTubeData\ChannelMetadata;
use App\Apis\YouTubeData\PlaylistMetadata;
use App\Apis\YouTubeData\VideoMetadata;
use App\Enums\AudioSourceKind;
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

    private function video(string $id, string $title, \DateTimeInterface $publishedAt, string $channelId = 'chan', string $channelName = 'Some Channel'): VideoMetadata
    {
        return new VideoMetadata(
            id: $id,
            title: $title,
            description: 'description of '.$title,
            publishedAt: $publishedAt,
            channel: new ChannelMetadata(id: $channelId, name: $channelName),
            durationSeconds: 600,
        );
    }

    #[Test]
    public function it_lists_a_channels_clips_through_its_uploads_playlist()
    {
        // search.list stops paging after ~500 results, so a channel is listed
        // through its uploads playlist instead — the "UC" id with a "UU"
        // prefix. Getting this wrong silently truncates a large channel.
        $this->fakeYouTubeData(
            channelMetadata: new ChannelMetadata(id: 'UCabc123', name: 'Some Channel'),
            playlistVideos: [
                $this->video('v1', 'Newest', now()->subDay()),
                $this->video('v2', 'Older', now()->subMonths(2)),
            ],
        );

        /** @var YouTube $youtube */
        $youtube = $this->app->make(YouTube::class);

        $clips = $youtube->getMetadataForAllClipsPublishedSince(
            'https://youtube.com/channel/UCabc123',
            now()->subYear(),
        );

        $this->assertCount(2, $clips);
        $this->assertEquals('Newest', $clips[0]->title);
        $this->assertEquals('https://youtube.com/watch?v=v1', $clips[0]->canonicalUrl);
    }

    #[Test]
    public function it_lists_a_playlists_clips_directly()
    {
        $this->fakeYouTubeData(playlistVideos: [
            $this->video('v1', 'First', now()->subDay()),
        ]);

        /** @var YouTube $youtube */
        $youtube = $this->app->make(YouTube::class);

        $clips = $youtube->getMetadataForAllClipsPublishedSince(
            'https://youtube.com/playlist?list=PLabc123',
            now()->subYear(),
        );

        $this->assertCount(1, $clips);
        $this->assertEquals('First', $clips[0]->title);
    }

    #[Test]
    public function it_credits_each_clip_to_the_channel_that_uploaded_it()
    {
        // A playlist can collect videos from several channels, so the source of
        // a clip is its own uploader — not whoever owns the playlist.
        $this->fakeYouTubeData(playlistVideos: [
            $this->video('v1', 'By Alice', now()->subDay(), 'UCalice', 'Alice'),
            $this->video('v2', 'By Bob', now()->subDays(2), 'UCbob', 'Bob'),
        ]);

        /** @var YouTube $youtube */
        $youtube = $this->app->make(YouTube::class);

        $clips = $youtube->getMetadataForAllClipsPublishedSince(
            'https://youtube.com/playlist?list=PLmixed',
            now()->subYear(),
        );

        $this->assertEquals('Alice', $clips[0]->source->name);
        $this->assertEquals('https://youtube.com/channel/UCalice', $clips[0]->source->canonicalUrl);
        $this->assertEquals('Bob', $clips[1]->source->name);
        $this->assertEquals('https://youtube.com/channel/UCbob', $clips[1]->source->canonicalUrl);
    }

    #[Test]
    public function it_gets_source_metadata_for_a_playlist()
    {
        $this->fakeYouTubeData(playlistMetadata: new PlaylistMetadata(
            id: 'PLabc123',
            title: 'Select Lectures',
            channel: new ChannelMetadata(id: 'UCowner', name: 'Lecture Channel'),
            itemCount: 42,
        ));

        /** @var YouTube $youtube */
        $youtube = $this->app->make(YouTube::class);

        $metadata = $youtube->getSourceMetadata('https://youtube.com/playlist?list=PLabc123');

        $this->assertEquals('Select Lectures', $metadata->name);
        $this->assertEquals('https://youtube.com/playlist?list=PLabc123', $metadata->canonicalUrl);
        $this->assertEquals(AudioSourceKind::Playlist, $metadata->kind);
        $this->assertEquals(42, $metadata->clipCount);

        // The playlist is named for what it collects, so the channel that owns
        // it is who the feed gets credited to.
        $this->assertEquals('Lecture Channel', $metadata->authorName);
    }

    #[Test]
    public function it_gets_source_metadata_for_a_channel()
    {
        $this->fakeYouTubeData(channelMetadata: new ChannelMetadata(
            id: 'UCabc123',
            name: 'Some Channel',
            videoCount: 864,
        ));

        /** @var YouTube $youtube */
        $youtube = $this->app->make(YouTube::class);

        $metadata = $youtube->getSourceMetadata('https://youtube.com/channel/UCabc123');

        $this->assertEquals('Some Channel', $metadata->name);
        $this->assertEquals(AudioSourceKind::Channel, $metadata->kind);
        $this->assertEquals(864, $metadata->clipCount);

        // A channel is its own author, so there's nothing separate to record.
        $this->assertNull($metadata->authorName);
    }

    #[Test]
    public function it_treats_a_watch_url_carrying_a_list_as_a_playlist_source()
    {
        // Sharing from within a playlist gives a watch URL with list= on it.
        $this->fakeYouTubeData(playlistMetadata: new PlaylistMetadata(
            id: 'PLabc123',
            title: 'Select Lectures',
            channel: new ChannelMetadata(id: 'UCowner', name: 'Lecture Channel'),
        ));

        /** @var YouTube $youtube */
        $youtube = $this->app->make(YouTube::class);

        $metadata = $youtube->getSourceMetadata('https://youtube.com/watch?v=abc&list=PLabc123');

        $this->assertEquals(AudioSourceKind::Playlist, $metadata->kind);
        $this->assertEquals('https://youtube.com/playlist?list=PLabc123', $metadata->canonicalUrl);
    }

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
