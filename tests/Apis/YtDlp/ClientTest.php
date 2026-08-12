<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Apis\YtDlp;

use App\Apis\YtDlp\Client;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientTest extends TestCase
{
    private const URL = 'https://youtube.com/watch?v=wp4i5g490wg7u';

    /**
     * Match the download yt-dlp runs when it goes straight to YouTube. The absence of a proxy argument is the thing
     * being matched here: it's what sits between the pacing arguments and the audio ones on the direct path.
     */
    private const DIRECT_DOWNLOAD = "*'--sleep-requests=1.5' '--extract-audio'*";

    private const PROXIED_DOWNLOAD = "*'--proxy=*";

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

        // An install with no Oxylabs account, which is most of them: the proxy costs money and needs signing up for.
        $config = $this->app->make(Repository::class);
        $config->set('services.oxylabs.residential.user', null);
        $config->set('services.oxylabs.residential.password', null);

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
}
