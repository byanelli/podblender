<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Apis\YtDlp;

use App\Apis\YtDlp\BotWallException;
use App\Apis\YtDlp\Client;
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

    /** What yt-dlp says when YouTube won't serve the address a request came from. Note the curly apostrophe. */
    private const BOT_WALL_ERROR = 'ERROR: [youtube] Sign in to confirm you’re not a bot. Use --cookies-from-browser.';

    /**
     * Match the download yt-dlp runs when it goes straight to YouTube. The absence of a proxy argument is the thing
     * being matched here: it's what sits between the pacing arguments and the audio ones on the direct path.
     */
    private const DIRECT_DOWNLOAD = "*'--sleep-requests=1.5' '--extract-audio'*";

    private const PROXIED_DOWNLOAD = "*'--proxy=*";

    protected function setUp(): void
    {
        parent::setUp();

        // The block on direct downloads is remembered in the cache, and the array store outlives a single resolve of
        // the client, so each test has to start out not knowing about one.
        Cache::flush();
    }

    private function isProxied(PendingProcess $process): bool
    {
        return collect($process->command)->contains(fn (string $argument) => Str::startsWith($argument, '--proxy='));
    }

    /**
     * Assert how many yt-dlp runs went straight to YouTube rather than through the proxy.
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
     * Assert that a command yt-dlp was run with can actually get past YouTube. Without a JavaScript runtime and a
     * proof-of-origin token provider, yt-dlp doesn't fail outright: it quietly falls back to whatever formats it can
     * still reach, until the day it can't reach any. That's a regression worth catching in a test rather than in a
     * podcast feed that stopped updating.
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

        $client->downloadAudio(self::URL);

        $this->assertFileExists($file);

        // A residential connection is the most credible address we have, so a download should only ever be proxied
        // after going directly has already failed.
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
        // The client backs off between attempts, which we don't want to actually wait for.
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

        $client->downloadAudio(self::URL);

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

        // The direct failure is the real one and should surface as itself, rather than becoming an error about
        // building a proxy URL out of credentials that were never going to be there.
        $this->expectException(ProcessFailedException::class);

        try {
            $client->downloadAudio(self::URL);
        } finally {
            // And it must not have tried to go through a proxy it hasn't got.
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
            $client->downloadAudio(self::URL);
        } catch (ProcessFailedException) {
            // Expected: this test is about how it retried, not that it failed.
        }

        $this->assertNotEmpty($sessions, 'Nothing was downloaded through the proxy.');

        // Retrying a download that a proxy just failed to make is only worth doing from a different address, and
        // asking for a new proxy URL per attempt is what gets us one.
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

        $client->downloadAudio(self::URL);
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

        $client->downloadAudio(self::URL);

        $this->assertFileExists($file);

        // A bot wall is a verdict on this host's address, not a hiccup, so a second and third attempt from the same
        // address would only spend the backoff to be told the same thing.
        $this->assertDirectDownloadsRan(1);

        $this->assertTrue(
            Cache::has(Client::DIRECT_BLOCKED_CACHE_KEY),
            'The refusal was not remembered, so the next download would discover it the slow way.',
        );
    }

    #[Test]
    public function it_skips_the_direct_attempt_entirely_while_the_block_is_remembered()
    {
        Sleep::fake();

        Cache::put(Client::DIRECT_BLOCKED_CACHE_KEY, true, now()->addHour());

        $file = null;

        Process::fake([self::PROXIED_DOWNLOAD => $this->fakeSuccessfulProxiedDownload($file)]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $client->downloadAudio(self::URL);

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

        $client->downloadAudio(self::URL);

        $this->assertDirectDownloadsRan(1);

        // A block that has lifted should cost us one wasted attempt to find out, and no more than that.
        $this->travel((int) config('services.ytdlp.direct_block_minutes') + 1)->minutes();

        $client->downloadAudio(self::URL);

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
            $client->downloadAudio(self::URL);

            $this->fail('Expected a BotWallException.');
        } catch (BotWallException) {
            // Expected.
        }

        // Having nothing to fall back to doesn't make the refusal any less worth remembering: the next download would
        // otherwise wait through the same failure.
        $this->assertTrue(Cache::has(Client::DIRECT_BLOCKED_CACHE_KEY));
    }

    #[Test]
    public function it_does_not_run_yt_dlp_at_all_when_the_block_is_remembered_and_there_is_no_proxy()
    {
        $this->withoutAResidentialProxy();

        Cache::put(Client::DIRECT_BLOCKED_CACHE_KEY, true, now()->addHour());

        Process::fake();

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $this->expectException(BotWallException::class);

        try {
            $client->downloadAudio(self::URL);
        } finally {
            // We know what the run would say, and a download that takes minutes to fail is worse than one that fails
            // at once.
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
            $client->downloadAudio(self::URL);
        } catch (ProcessFailedException) {
            // Expected: this test is about how many attempts it made, not that it failed.
        }

        // The pool hands out a fresh address per attempt, so a refusal of one address says nothing about the next.
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
            $client->downloadAudio(self::URL);
        } catch (ProcessFailedException $e) {
            $this->assertNotInstanceOf(BotWallException::class, $e);
        }

        // An age check is an ordinary failure: it gets the ordinary backoff, and it isn't remembered as a block.
        $this->assertDirectDownloadsRan(3);

        $this->assertFalse(Cache::has(Client::DIRECT_BLOCKED_CACHE_KEY));
    }

    /**
     * An install with no Oxylabs account, which is most of them: the proxy costs money and needs signing up for.
     */
    private function withoutAResidentialProxy(): void
    {
        $config = $this->app->make(Repository::class);
        $config->set('services.oxylabs.residential.user', null);
        $config->set('services.oxylabs.residential.password', null);
    }
}
