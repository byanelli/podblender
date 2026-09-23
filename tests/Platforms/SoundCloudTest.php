<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Platforms;

use App\Enums\AudioSourceType;
use App\Platforms\Contracts\RemoteImageThumbnail;
use App\Platforms\Exceptions\ContentUnavailableException;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\SoundCloud;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SoundCloudTest extends TestCase
{
    /** yt-dlp's error for a Go+ track once the format selector has excluded its preview. */
    private const PREVIEW_ONLY_ERROR = 'ERROR: [soundcloud] 2321029664: Requested format is not available. Use --list-formats for a list of available formats';

    protected function setUp(): void
    {
        parent::setUp();

        // A remembered block would send queries through the proxy.
        Cache::flush();
    }

    private function soundCloud(): SoundCloud
    {
        return $this->app->make(SoundCloud::class);
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__."/fixtures/soundcloud/$name");
    }

    /**
     * Fake every yt-dlp run with the same result, and record each command.
     *
     * @param  array<int, array<int, string>>  $commands
     */
    private function fakeYtDlp(string $output = '', int $exitCode = 0, string $errorOutput = '', ?array &$commands = null): void
    {
        $commands = [];

        Process::fake(['*' => function (PendingProcess $process) use ($output, $exitCode, $errorOutput, &$commands) {
            $commands[] = $process->command;

            return Process::result(output: $output, errorOutput: $errorOutput, exitCode: $exitCode);
        }]);
    }

    #[Test]
    public function it_gets_a_tracks_metadata()
    {
        $this->fakeYtDlp($this->fixture('track.json'), commands: $commands);

        $metadata = $this->soundCloud()->getClipMetadata('https://m.soundcloud.com/forss/flickermood?in=forss/sets/soulhack&utm_source=clipboard');

        $this->assertEquals('Flickermood', $metadata->title);
        $this->assertEquals('https://soundcloud.com/forss/flickermood', $metadata->canonicalUrl);
        $this->assertEquals(1190472346, $metadata->publishedAt->getTimestamp());
        $this->assertStringStartsWith('From the Soulhack album, recently featured in this ad https://', $metadata->description);
        $this->assertNotNull($metadata->estimatedDownloadTime);
        $this->assertEquals(
            new RemoteImageThumbnail('https://i1.sndcdn.com/artworks-000067273316-smsiqx-t500x500.jpg'),
            $metadata->thumbnail,
        );

        // The clip's source is the uploader, under the same URL as a subscription to the uploader's profile.
        $this->assertEquals('Forss', $metadata->source->name);
        $this->assertEquals('https://soundcloud.com/forss/tracks', $metadata->source->canonicalUrl);
        $this->assertEquals(AudioSourceType::Channel, $metadata->source->type);

        $this->assertCount(1, $commands);
        $this->assertContains('--format', $commands[0]);
        $this->assertEquals('https://soundcloud.com/forss/flickermood', collect($commands[0])->last());
    }

    #[Test]
    public function it_keeps_a_private_tracks_token()
    {
        $this->fakeYtDlp($this->fixture('track.json'), commands: $commands);

        $this->soundCloud()->getClipMetadata('https://soundcloud.com/forss/flickermood/s-AbCdEf123?si=abc');

        $this->assertEquals('https://soundcloud.com/forss/flickermood/s-AbCdEf123', collect($commands[0])->last());
    }

    #[Test]
    public function it_tells_the_user_a_track_is_preview_only()
    {
        $this->fakeYtDlp(exitCode: 1, errorOutput: self::PREVIEW_ONLY_ERROR);

        $this->expectException(PlatformException::class);
        $this->expectExceptionMessage('only available as a preview to SoundCloud Go+ subscribers');

        $this->soundCloud()->getClipMetadata('https://soundcloud.com/octobersveryown/national-treasures-1');
    }

    #[Test]
    public function it_rejects_a_profile_link_given_for_a_clip_without_running_yt_dlp()
    {
        $this->fakeYtDlp();

        foreach (['https://soundcloud.com/forss', 'https://soundcloud.com/forss/sets/soulhack', 'https://soundcloud.com/forss/likes'] as $url) {
            try {
                $this->soundCloud()->getClipMetadata($url);

                $this->fail("Accepted $url as a track");
            } catch (PlatformException $e) {
                $this->assertStringContainsString('not a track', $e->getMessage());
            }
        }

        // yt-dlp would fetch every item of a profile or set.
        Process::assertNothingRan();
    }

    #[Test]
    public function it_rejects_a_track_link_given_for_a_subscription()
    {
        $this->fakeYtDlp();

        $this->expectException(PlatformException::class);
        $this->expectExceptionMessage('link is to a single track');

        $this->soundCloud()->getSourceMetadata('https://soundcloud.com/forss/flickermood');
    }

    #[Test]
    public function it_subscribes_to_a_users_own_tracks()
    {
        $this->fakeYtDlp($this->fixture('user-tracks.json'), commands: $commands);

        // The profile page also lists reposts of other users' tracks.
        $metadata = $this->soundCloud()->getSourceMetadata('https://www.soundcloud.com/Forss');

        $this->assertEquals('Forss', $metadata->name);
        $this->assertEquals('Forss', $metadata->authorName);
        $this->assertEquals('https://soundcloud.com/forss/tracks', $metadata->canonicalUrl);
        $this->assertEquals(AudioSourceType::Channel, $metadata->type);

        $this->assertEquals('https://soundcloud.com/forss/tracks', collect($commands[0])->last());
    }

    #[Test]
    public function it_subscribes_to_a_set()
    {
        $this->fakeYtDlp($this->fixture('set.json'));

        $metadata = $this->soundCloud()->getSourceMetadata('https://soundcloud.com/forss/sets/soulhack');

        $this->assertEquals('Soulhack', $metadata->name);
        $this->assertEquals('Forss', $metadata->authorName);
        $this->assertEquals('https://soundcloud.com/forss/sets/soulhack', $metadata->canonicalUrl);
        $this->assertEquals(AudioSourceType::Playlist, $metadata->type);
        $this->assertEquals(11, $metadata->clipCount);
    }

    #[Test]
    public function it_stops_listing_a_users_tracks_at_the_first_older_one()
    {
        // yt-dlp exits with 101 when --break-match-filters stops the listing.
        $this->fakeYtDlp($this->fixture('user-entries.jsonl'), exitCode: 101, commands: $commands);

        $since = now()->setTimestamp(1338000000);

        $clips = $this->soundCloud()->getMetadataForAllClipsPublishedSince('https://soundcloud.com/forss/tracks', $since);

        $this->assertCount(4, $clips);
        $this->assertEquals('Lux Aeterna', $clips[0]->title);
        $this->assertEquals('https://soundcloud.com/forss/lux-aeterna', $clips[0]->canonicalUrl);
        $this->assertEquals('https://soundcloud.com/forss/tracks', $clips[0]->source->canonicalUrl);

        $command = implode(' ', $commands[0]);
        $this->assertStringContainsString('--lazy-playlist --break-match-filters timestamp>=1338000000', $command);
        $this->assertStringContainsString('soundcloud:formats=none', $command);
    }

    #[Test]
    public function it_filters_every_track_of_a_set()
    {
        $this->fakeYtDlp($this->fixture('user-entries.jsonl'), commands: $commands);

        $this->soundCloud()->getMetadataForAllClipsPublishedSince(
            'https://soundcloud.com/forss/sets/soulhack',
            now()->setTimestamp(1338000000),
        );

        // A set isn't in date order, so the listing mustn't stop at the first older track.
        $command = implode(' ', $commands[0]);
        $this->assertStringContainsString('--match-filters timestamp>=1338000000', $command);
        $this->assertStringNotContainsString('--break-match-filters', $command);
    }

    #[Test]
    public function it_follows_a_short_link_to_the_track()
    {
        Http::fake([
            'on.soundcloud.com/*' => Http::response('', 302, [
                'Location' => 'https://soundcloud.com/forss/flickermood?si=0123&utm_medium=text&utm_source=clipboard',
            ]),
        ]);

        $this->fakeYtDlp($this->fixture('track.json'), commands: $commands);

        $this->soundCloud()->getClipMetadata('https://on.soundcloud.com/AbC123xYz');

        $this->assertEquals('https://soundcloud.com/forss/flickermood', collect($commands[0])->last());
    }

    #[Test]
    public function it_downloads_with_its_format_selector()
    {
        $this->fakeYtDlp(commands: $commands);

        $this->soundCloud()->downloadAudio('https://soundcloud.com/forss/flickermood');

        $this->assertContains('--format', $commands[0]);
        $this->assertStringContainsString('[format_id!*=preview]', implode(' ', $commands[0]));

        // The pause between requests is for YouTube's bot detection, and would slow down adding a track.
        $this->assertStringNotContainsString('--sleep-requests', implode(' ', $commands[0]));
    }

    #[Test]
    public function it_reports_a_preview_only_download_as_unavailable()
    {
        // Unavailable content isn't retried, so this fails on the first run.
        $this->fakeYtDlp(exitCode: 1, errorOutput: self::PREVIEW_ONLY_ERROR);

        $this->expectException(ContentUnavailableException::class);

        $this->soundCloud()->downloadAudio('https://soundcloud.com/octobersveryown/national-treasures-1');
    }
}
