<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Apis\YtDlp;

use App\Apis\YtDlp\BotWallException;
use App\Apis\YtDlp\Client;
use App\Apis\YtDlp\Site;
use App\Apis\YtDlp\UnavailableContentException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientTest extends TestCase
{
    private const URL = 'https://youtube.com/watch?v=wp4i5g490wg7u';

    /** yt-dlp's error when YouTube refuses the requesting address. Note the curly apostrophe. */
    private const BOT_WALL_ERROR = 'ERROR: [youtube] Sign in to confirm you’re not a bot. Use --cookies-from-browser.';

    /**
     * Matches a direct download. The proxy argument goes between the pacing arguments and the audio arguments, so
     * these two are adjacent only when there is no proxy.
     */
    private const DIRECT_DOWNLOAD = "*'--sleep-requests=1.5' '--extract-audio'*";

    private const PROXIED_DOWNLOAD = "*'--proxy=*";

    /**
     * YouTube's settings, since its bot wall is the one these tests simulate.
     */
    private function site(): Site
    {
        return new Site(
            name: 'YouTube',
            botWallMarkers: ["confirm you're not a bot", 'confirm you’re not a bot'],
            unavailableMarkers: ['members-only'],
            secondsBetweenRequests: 1.5,
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        // The block on direct downloads is cached, so each test starts with none recorded.
        Cache::flush();
    }

    private function isProxied(PendingProcess $process): bool
    {
        return collect($process->command)->contains(fn (string $argument) => Str::startsWith($argument, '--proxy='));
    }

    /**
     * Assert how many yt-dlp runs were not proxied.
     */
    private function assertDirectDownloadsRan(int $times): void
    {
        Process::assertRanTimes(fn (PendingProcess $process) => ! $this->isProxied($process), $times);
    }

    /**
     * Fake a proxied download that writes its file, and record where it wrote it.
     */
    private function fakeSuccessfulProxiedDownload(?string &$file): callable
    {
        return function (PendingProcess $process) use (&$file) {
            $file = collect($process->command)->first(fn ($s) => Str::endsWith($s, '.mp3'));

            touch($file);

            return Process::result();
        };
    }

    /**
     * Assert that the command includes a JavaScript runtime and the proof-of-origin token provider. Without them
     * yt-dlp still exits successfully but can only download a reduced set of formats, so production would not show
     * the regression until no formats were left.
     */
    private function assertCommandCanReachYouTube(array $command): void
    {
        $arguments = collect($command);

        $this->assertTrue(
            $arguments->contains(fn (string $a) => Str::startsWith($a, '--js-runtimes=deno:')),
            'yt-dlp was run without being told where to find Deno.',
        );

        $this->assertTrue(
            $arguments->contains(fn (string $a) => Str::startsWith($a, '--plugin-dirs=')),
            'yt-dlp was run without the proof-of-origin token provider plugin directory.',
        );

        $this->assertTrue(
            $arguments->contains(fn (string $a) => Str::contains($a, 'youtubepot-bgutilcli:cli_path=')),
            'yt-dlp was run without being told where to find the proof-of-origin token executable.',
        );
    }

    #[Test]
    public function it_downloads_audio_directly_without_a_proxy()
    {
        $file = '';

        Process::fake([self::DIRECT_DOWNLOAD => function (PendingProcess $process) use (&$file) {
            $file = collect($process->command)->first(fn ($s) => Str::endsWith($s, '.mp3'));

            touch($file);

            return Process::result();
        }]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $client->downloadAudio(self::URL, $this->site());

        $this->assertFileExists($file);

        // A download is proxied only after a direct attempt has failed.
        Process::assertRan(function (PendingProcess $process) {
            $this->assertCommandCanReachYouTube($process->command);

            $this->assertFalse(
                collect($process->command)->contains(fn (string $a) => Str::startsWith($a, '--proxy=')),
                'The first attempt at a download was proxied.',
            );

            return true;
        });
    }

    #[Test]
    public function it_falls_back_to_the_residential_proxy_when_downloading_directly_fails()
    {
        // The client backs off between attempts.
        Sleep::fake();

        $file = '';

        Process::fake([
            self::DIRECT_DOWNLOAD  => Process::result(exitCode: 1, errorOutput: 'Sign in to confirm you’re not a bot'),

            self::PROXIED_DOWNLOAD => function (PendingProcess $process) use (&$file) {
                $file = collect($process->command)->first(fn ($s) => Str::endsWith($s, '.mp3'));

                touch($file);

                return Process::result();
            },
        ]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $client->downloadAudio(self::URL, $this->site());

        $this->assertFileExists($file);

        Process::assertRan(fn (PendingProcess $process) => collect($process->command)
            ->contains(fn (string $a) => Str::startsWith($a, '--proxy=')));
    }

    #[Test]
    public function it_gives_up_on_the_direct_failure_when_no_proxy_is_configured()
    {
        Sleep::fake();

        $this->withoutAResidentialProxy();

        Process::fake([
            self::DIRECT_DOWNLOAD => Process::result(exitCode: 1, errorOutput: 'Sign in to confirm you’re not a bot'),
        ]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        // The direct failure is thrown, and not an error about building a proxy URL from missing credentials.
        $this->expectException(ProcessFailedException::class);

        try {
            $client->downloadAudio(self::URL, $this->site());
        } finally {
            Process::assertNotRan(fn (PendingProcess $process) => collect($process->command)
                ->contains(fn (string $a) => Str::startsWith($a, '--proxy=')));
        }
    }

    #[Test]
    public function it_asks_the_proxy_for_a_new_address_on_every_attempt()
    {
        Sleep::fake();

        $sessions = [];

        Process::fake(['*' => function (PendingProcess $process) use (&$sessions) {
            foreach ($process->command as $argument) {
                if (Str::startsWith($argument, '--proxy=') && preg_match('/-sessid-(\w+)-/', $argument, $matches)) {
                    $sessions[] = $matches[1];
                }
            }

            return Process::result(exitCode: 1, errorOutput: 'Sign in to confirm you’re not a bot');
        }]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        try {
            $client->downloadAudio(self::URL, $this->site());
        } catch (ProcessFailedException) {
            // Expected. The assertions below are about the retries.
        }

        $this->assertNotEmpty($sessions, 'Nothing was downloaded through the proxy.');

        // Each attempt requests a new proxy URL, and a new session id gives a different address.
        $this->assertSameSize(
            $sessions,
            array_unique($sessions),
            'Two attempts shared a session, and so would have retried from the same address.',
        );
    }

    #[Test]
    public function it_gives_up_when_the_residential_proxy_fails_too()
    {
        Sleep::fake();

        Process::fake([
            '*' => Process::result(exitCode: 1, errorOutput: 'Sign in to confirm you’re not a bot'),
        ]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $this->expectException(ProcessFailedException::class);

        $client->downloadAudio(self::URL, $this->site());
    }

    #[Test]
    public function it_does_not_retry_a_direct_download_that_hit_a_bot_wall()
    {
        Sleep::fake();

        $file = null;

        Process::fake([
            self::DIRECT_DOWNLOAD  => Process::result(exitCode: 1, errorOutput: self::BOT_WALL_ERROR),
            self::PROXIED_DOWNLOAD => $this->fakeSuccessfulProxiedDownload($file),
        ]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $client->downloadAudio(self::URL, $this->site());

        $this->assertFileExists($file);

        // A bot wall applies to the host's address, so retrying from the same address will fail.
        $this->assertDirectDownloadsRan(1);

        $this->assertTrue(
            Cache::has(Client::directBlockedCacheKey($this->site())),
            'The refusal was not remembered, so the next download would discover it the slow way.',
        );
    }

    #[Test]
    public function it_skips_the_direct_attempt_entirely_while_the_block_is_remembered()
    {
        Sleep::fake();

        Cache::put(Client::directBlockedCacheKey($this->site()), true, now()->addHour());

        $file = null;

        Process::fake([self::PROXIED_DOWNLOAD => $this->fakeSuccessfulProxiedDownload($file)]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $client->downloadAudio(self::URL, $this->site());

        $this->assertFileExists($file);

        $this->assertDirectDownloadsRan(0);
    }

    #[Test]
    public function it_tries_directly_again_once_the_remembered_block_expires()
    {
        Sleep::fake();

        $file = null;

        Process::fake([
            self::DIRECT_DOWNLOAD  => Process::result(exitCode: 1, errorOutput: self::BOT_WALL_ERROR),
            self::PROXIED_DOWNLOAD => $this->fakeSuccessfulProxiedDownload($file),
        ]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $client->downloadAudio(self::URL, $this->site());

        $this->assertDirectDownloadsRan(1);

        // Travel past the block's expiry.
        $this->travel((int) config('services.ytdlp.direct_block_minutes') + 1)->minutes();

        $client->downloadAudio(self::URL, $this->site());

        $this->assertDirectDownloadsRan(2);
    }

    #[Test]
    public function it_reports_a_bot_wall_as_itself_when_no_proxy_is_configured()
    {
        Sleep::fake();

        $this->withoutAResidentialProxy();

        Process::fake([self::DIRECT_DOWNLOAD => Process::result(exitCode: 1, errorOutput: self::BOT_WALL_ERROR)]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        try {
            $client->downloadAudio(self::URL, $this->site());

            $this->fail('Expected a BotWallException.');
        } catch (BotWallException) {
            // Expected.
        }

        // The block is cached even with no proxy, so the next download fails without running yt-dlp.
        $this->assertTrue(Cache::has(Client::directBlockedCacheKey($this->site())));
    }

    #[Test]
    public function it_does_not_run_yt_dlp_at_all_when_the_block_is_remembered_and_there_is_no_proxy()
    {
        $this->withoutAResidentialProxy();

        Cache::put(Client::directBlockedCacheKey($this->site()), true, now()->addHour());

        Process::fake();

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $this->expectException(BotWallException::class);

        try {
            $client->downloadAudio(self::URL, $this->site());
        } finally {
            // The outcome is already known, so the download fails at once and not after minutes of attempts.
            Process::assertNothingRan();
        }
    }

    #[Test]
    public function it_retries_a_proxied_download_that_hit_a_bot_wall()
    {
        Sleep::fake();

        Process::fake(['*' => Process::result(exitCode: 1, errorOutput: self::BOT_WALL_ERROR)]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        try {
            $client->downloadAudio(self::URL, $this->site());
        } catch (ProcessFailedException) {
            // Expected. The assertion below is about the number of attempts.
        }

        // Each proxied attempt uses a different address, so a refusal of one does not predict the next.
        Process::assertRanTimes(fn (PendingProcess $process) => $this->isProxied($process), 3);
    }

    #[Test]
    public function it_does_not_mistake_an_age_check_for_a_bot_wall()
    {
        Sleep::fake();

        Process::fake([
            self::DIRECT_DOWNLOAD  => Process::result(exitCode: 1, errorOutput: 'ERROR: Sign in to confirm your age'),
            self::PROXIED_DOWNLOAD => Process::result(exitCode: 1, errorOutput: 'ERROR: Sign in to confirm your age'),
        ]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        try {
            $client->downloadAudio(self::URL, $this->site());
        } catch (ProcessFailedException $e) {
            $this->assertNotInstanceOf(BotWallException::class, $e);
        }

        // An age check is retried with backoff like any other failure and is not cached as a block.
        $this->assertDirectDownloadsRan(3);

        $this->assertFalse(Cache::has(Client::directBlockedCacheKey($this->site())));
    }

    #[Test]
    public function it_reports_unavailable_content_without_retrying()
    {
        Process::fake(['*' => Process::result(exitCode: 1, errorOutput: 'ERROR: [youtube] abc: This video is available to this channel\'s members-only')]);

        try {
            $this->app->make(Client::class)->downloadAudio(self::URL, $this->site());

            $this->fail('No exception was thrown');
        } catch (UnavailableContentException) {
        }

        Process::assertRanTimes(fn () => true, 1);
    }

    #[Test]
    public function it_reads_an_items_info()
    {
        Process::fake(['*' => Process::result(output: json_encode([
            '_type'        => 'video',
            'title'        => 'Flickermood',
            'webpage_url'  => 'https://soundcloud.com/forss/flickermood',
            'timestamp'    => 1190472346,
            'duration'     => 213.886,
            'uploader'     => 'Forss',
            'uploader_url' => 'https://soundcloud.com/forss',
            'thumbnails'   => [['id' => 't500x500', 'url' => 'https://i1.sndcdn.com/a-t500x500.jpg']],
        ]))]);

        $info = $this->app->make(Client::class)->getInfo('https://soundcloud.com/forss/flickermood', $this->site(), ['--flat-playlist']);

        $this->assertFalse($info->isPlaylist);
        $this->assertEquals('Flickermood', $info->title);
        $this->assertEquals(1190472346, $info->timestamp?->getTimestamp());
        $this->assertEquals(213.886, $info->durationSeconds);
        $this->assertEquals('Forss', $info->uploader);
        $this->assertEquals(['t500x500' => 'https://i1.sndcdn.com/a-t500x500.jpg'], $info->thumbnails);

        Process::assertRan(fn (PendingProcess $process) => in_array('--dump-single-json', $process->command)
            && in_array('--flat-playlist', $process->command)
            && ! $this->isProxied($process));
    }

    #[Test]
    public function it_reads_a_listing_that_a_filter_stopped_early()
    {
        $lines = collect(['one', 'two'])
            ->map(fn (string $title) => json_encode(['title' => $title, 'webpage_url' => "https://soundcloud.com/u/$title"]))
            ->join("\n");

        // yt-dlp's exit code when --break-match-filters stops a playlist.
        Process::fake(['*' => Process::result(output: $lines."\n", exitCode: 101)]);

        $entries = $this->app->make(Client::class)->getEntries('https://soundcloud.com/u/tracks', $this->site());

        $this->assertEquals(['one', 'two'], collect($entries)->pluck('title')->all());
    }

    #[Test]
    public function it_fails_a_listing_that_exited_with_an_error()
    {
        Process::fake(['*' => Process::result(exitCode: 1, errorOutput: 'ERROR: Unable to download JSON metadata')]);

        $this->expectException(ProcessFailedException::class);

        $this->app->make(Client::class)->getEntries('https://soundcloud.com/u/tracks', $this->site());
    }

    #[Test]
    public function it_repeats_a_query_through_the_proxy_after_a_bot_wall()
    {
        Process::fake(['*' => fn (PendingProcess $process) => $this->isProxied($process)
            ? Process::result(output: json_encode(['title' => 'Video', 'webpage_url' => self::URL]))
            : Process::result(exitCode: 1, errorOutput: self::BOT_WALL_ERROR)]);

        $info = $this->app->make(Client::class)->getInfo(self::URL, $this->site());

        $this->assertEquals('Video', $info->title);

        // A query may come from a web request, so the direct attempt isn't retried.
        $this->assertDirectDownloadsRan(1);
        $this->assertTrue(Cache::has(Client::directBlockedCacheKey($this->site())));
    }

    #[Test]
    public function it_remembers_a_block_for_that_site_only()
    {
        Cache::put(Client::directBlockedCacheKey($this->site()), true, now()->addHour());

        Process::fake(['*' => Process::result(output: json_encode(['title' => 'Track', 'webpage_url' => 'https://soundcloud.com/u/t']))]);

        $this->app->make(Client::class)->getInfo('https://soundcloud.com/u/t', new Site(name: 'SoundCloud'));

        $this->assertDirectDownloadsRan(1);
    }

    /**
     * Simulate an install with no residential proxy account. Every provider's credentials are cleared, so the tests
     * don't depend on which provider the config selects.
     */
    private function withoutAResidentialProxy(): void
    {
        $config = $this->app->make(Repository::class);

        $config->set('services.oxylabs.residential.user', null);
        $config->set('services.oxylabs.residential.password', null);

        $config->set('services.dataimpulse.residential.user', null);
        $config->set('services.dataimpulse.residential.password', null);
    }
}
