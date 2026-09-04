<?php

namespace App\Apis\YtDlp;

use App\Proxies\Contracts\ProxyConfig;
use App\Proxies\Contracts\ResidentialProxyConfig;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\Factory;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * From GitHub: "yt-dlp is a feature-rich command-line audio/video downloader with support for thousands of sites. The
 * project is a fork of youtube-dl based on the now inactive youtube-dlc."
 *
 * A note on getting YouTube downloads to succeed, since it's the whole reason this class looks the way it does.
 * YouTube decides whether to serve a request based on three things, roughly in order of how much they matter:
 *
 *   1. Whether the request carries a valid proof-of-origin token (see scripts/install-bgutil-pot.php).
 *   2. Whether the reputation of the IP it comes from looks like a person's. Datacenter ranges, which includes every
 *      commercial VPN endpoint, are flagged regardless of everything else, which is why we prefer to make requests
 *      directly from the host and fall back to a residential proxy rather than routing through a VPN.
 *   3. Whether the requests arrive in a burst. Hence the pacing below and in App\Jobs\DownloadAndStoreAudioClip.
 *
 * When YouTube refuses an address outright it says so ("Sign in to confirm you're not a bot"), and the refusal holds
 * for hours, so this class treats it as an answer rather than a hiccup: the direct attempt isn't retried, and the
 * block is remembered in the cache so that every later download skips straight to the residential proxy until the
 * flag expires.
 */
readonly class Client
{
    const int DOWNLOAD_TIMEOUT = 1800;

    /**
     * Seconds to wait between the individual requests yt-dlp makes while extracting a single video. Pacing *between*
     * videos is the job's responsibility, not ours: it's the layer that knows what else is queued up.
     */
    const string SLEEP_BETWEEN_REQUESTS = '1.5';

    /**
     * Where we remember that YouTube is refusing this host's address, so the next download doesn't have to find out
     * the slow way.
     */
    const string DIRECT_BLOCKED_CACHE_KEY = 'yt-dlp:direct-blocked';

    /**
     * Phrases that mean "YouTube doesn't believe this address belongs to a person". Matched case-insensitively
     * against yt-dlp's error output. yt-dlp writes the apostrophe as a curly one, but not everywhere and not in
     * every version, so both forms are listed; add to this list as new wordings turn up.
     *
     * @var array<int, string>
     */
    const array BOT_WALL_MARKERS = [
        'confirm you’re not a bot',
        "confirm you're not a bot",
    ];

    public function __construct(
        private Application $app,
        private LoggerInterface $logger,
        private Factory $processFactory,
        private ResidentialProxyConfig $residentialProxy,
        private Cache $cache,
        private Config $config,
    ) {}

    private function getVendorBinPath(): string
    {
        return $this->app->basePath('vendor/bin');
    }

    private function getVendoredPath(string $path): string
    {
        return $this->app->basePath("vendor/$path");
    }

    /**
     * @param  array<int, string>  $args
     */
    private function run(int $timeout, array $args): ProcessResult
    {
        return $this->processFactory
            ->newPendingProcess()
            ->timeout($timeout)
            ->path($this->getVendorBinPath())
            ->run(array_merge(['./yt-dlp'], $args))
            ->throw();
    }

    /**
     * Arguments that every call to yt-dlp needs. They're only meaningful to the YouTube extractor, but they're
     * harmless elsewhere, so there's no need to vary them per platform.
     *
     * Note that all three paths are absolute. yt-dlp discovers Deno and the token provider on the PATH, and we can't
     * assume the queue worker's PATH contains a directory inside this project, so we tell it exactly where to look.
     *
     * @return array<int, string>
     */
    private function getCommonArgs(): array
    {
        return [
            // Without an external JavaScript runtime, yt-dlp can't solve YouTube's challenges and silently falls back
            // to a limited set of formats.
            "--js-runtimes=deno:{$this->getVendoredPath('bin/deno')}",

            // Load the proof-of-origin token provider plugin, and tell it where its executable lives.
            "--plugin-dirs={$this->getVendoredPath('yt-dlp-plugins')}",
            "--extractor-args=youtubepot-bgutilcli:cli_path={$this->getVendoredPath('bin/bgutil-pot')}",

            '--sleep-requests='.self::SLEEP_BETWEEN_REQUESTS,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function getAudioArgs(string $outputPath): array
    {
        return [
            '--extract-audio',
            "--ffmpeg-location={$this->getVendoredPath('bin/ffmpeg')}",
            '--audio-format=mp3',
            '--audio-quality=2',
            '-o', $outputPath,
        ];
    }

    /**
     * Note that this asks the proxy for a URL each time it's called, which is once per download attempt. That's what
     * gives a rotating pool one address per download rather than one per request, which a download can't survive.
     *
     * @return array<int, string>
     */
    private function getProxyArgs(ProxyConfig $proxy): array
    {
        return array_filter([
            "--proxy={$proxy->getUrlForDownload()}",

            // Only for proxies that can't leave TLS end-to-end. Never passed when talking to YouTube directly.
            $proxy->requiresInsecureTls() ? '--no-check-certificates' : null,
        ]);
    }

    private function downloadFailedDueToMembersOnlyContent(ProcessResult $result): bool
    {
        // todo: more accurate detection?
        return str_contains($result->errorOutput(), 'members-only');
    }

    /**
     * Whether YouTube refused the request because of where it came from, rather than because of anything about the
     * video. Note that "confirm your age" is a different refusal with a different answer, and mustn't match here.
     */
    private function downloadFailedDueToBotWall(ProcessResult $result): bool
    {
        return Str::contains($result->errorOutput(), self::BOT_WALL_MARKERS, ignoreCase: true);
    }

    /**
     * Download the audio at $url, optionally by way of a proxy. Passing no proxy means talking to YouTube directly
     * from this host, which is the preferable case: a residential connection is the most credible address we have.
     *
     * @throws ProcessFailedException
     * @throws MembersOnlyContentException
     * @throws BotWallException
     */
    private function runDownload(string $url, string $outputPath, ?ProxyConfig $proxy = null): ProcessResult
    {
        try {
            return $this->run(
                // Double the download timeout when proxied, because a proxy may be slower.
                is_null($proxy) ? self::DOWNLOAD_TIMEOUT : self::DOWNLOAD_TIMEOUT * 2,
                array_merge(
                    $this->getCommonArgs(),
                    is_null($proxy) ? [] : $this->getProxyArgs($proxy),
                    $this->getAudioArgs($outputPath),
                    [$url],
                ),
            );
        } catch (ProcessFailedException $e) {
            if ($this->downloadFailedDueToMembersOnlyContent($e->result)) {
                $this->logger->error("Couldn't download $url because it's a members-only video");

                throw new MembersOnlyContentException;
            } elseif ($this->downloadFailedDueToBotWall($e->result)) {
                $this->logger->warning("Couldn't download $url because YouTube refused the address it came from");

                throw new BotWallException($e->result);
            } else {
                throw $e;
            }
        }
    }

    /**
     * $retryOnBotWall is false for the direct attempt and true for the proxied one. A bot wall is a verdict on the
     * address the request came from, so trying again from this host's single address just spends the backoff to be
     * told the same thing; a rotating residential pool, on the other hand, hands out a fresh address per attempt.
     *
     * @throws ProcessFailedException
     * @throws MembersOnlyContentException
     * @throws BotWallException
     */
    private function retryWithExponentialBackoff(
        callable $callback,
        bool $retryOnBotWall = true,
        int $retryTimes = 3,
        int $baseSleepSeconds = 60,
    ): mixed {
        return retry(
            times: $retryTimes,
            callback: $callback,
            sleepMilliseconds: fn (int $attempts) => $baseSleepSeconds * pow(2, $attempts - 1) * 1000,
            when: fn (\Throwable $t) => match (true) {
                // No point in retrying if the content is members-only.
                $t instanceof MembersOnlyContentException => false,
                $t instanceof BotWallException            => $retryOnBotWall,
                default                                   => true,
            },
        );
    }

    /**
     * Whether we've already been told that YouTube won't serve this host's address. The flag expires on its own, so
     * a block that lifts early costs us nothing worse than one wasted attempt once it does.
     */
    private function directDownloadsAreBlocked(): bool
    {
        return (bool) $this->cache->get(self::DIRECT_BLOCKED_CACHE_KEY, false);
    }

    private function rememberDirectDownloadsAreBlocked(): void
    {
        $minutes = (int) $this->config->get('services.ytdlp.direct_block_minutes');

        $this->cache->put(self::DIRECT_BLOCKED_CACHE_KEY, true, now()->addMinutes($minutes));
    }

    /**
     * @throws ProcessFailedException
     * @throws MembersOnlyContentException
     * @throws BotWallException
     */
    private function downloadThroughResidentialProxy(string $url, string $outputPath): void
    {
        try {
            $this->retryWithExponentialBackoff(
                fn () => $this->runDownload($url, $outputPath, $this->residentialProxy)
            );

            $this->logger->info("Successfully downloaded $url with residential proxy");
        } catch (ProcessFailedException $e) {
            $this->logger->error("Failed to download $url with residential proxy; giving up");

            throw $e;
        }
    }

    /**
     * @throws ProcessFailedException
     * @throws MembersOnlyContentException
     * @throws BotWallException
     */
    public function downloadAudio(string $url): string
    {
        $filename = Uuid::uuid4()->toString();

        $outputPath = sys_get_temp_dir()."/$filename.mp3";

        if ($this->directDownloadsAreBlocked()) {
            $this->logger->info(
                "Not downloading $url directly: YouTube is refusing this host's address until the block expires"
            );

            // Nothing to fall back to, and we already know what a direct attempt would say, so don't spend a yt-dlp
            // run on being told again.
            if (! $this->residentialProxy->isConfigured()) {
                $this->logger->error(
                    "Can't download $url: YouTube is refusing this host's address and no residential proxy is "
                    .'configured to fall back to'
                );

                throw BotWallException::withoutRunningYtDlp($url);
            }

            $this->downloadThroughResidentialProxy($url, $outputPath);

            return $outputPath;
        }

        try {
            // Try to run the download using exponential backoff directly from this host.
            $this->retryWithExponentialBackoff(
                fn () => $this->runDownload($url, $outputPath),
                retryOnBotWall: false,
            );

            $this->logger->info("Successfully downloaded $url directly");
        } catch (BotWallException $e) {
            // Remember the refusal either way: it holds for hours, and the next download shouldn't have to spend an
            // attempt discovering it.
            $this->rememberDirectDownloadsAreBlocked();

            if (! $this->residentialProxy->isConfigured()) {
                $this->logger->error(
                    "Failed to download $url: YouTube is refusing this host's address and no residential proxy is "
                    .'configured to fall back to'
                );

                throw $e;
            }

            $this->logger->warning(
                "YouTube is refusing this host's address; downloading $url through the residential proxy instead"
            );

            $this->downloadThroughResidentialProxy($url, $outputPath);
        } catch (ProcessFailedException $e) {
            // The proxy is an optional extra, and an install without an account for it has nothing to fall back to.
            // Report the direct failure as the real one rather than dressing it up as a proxy problem.
            if (! $this->residentialProxy->isConfigured()) {
                $this->logger->error(
                    "Failed to download $url directly, and no residential proxy is configured to fall back to"
                );

                throw $e;
            }

            $this->logger->warning("Failed to download $url directly; trying residential proxy");

            $this->downloadThroughResidentialProxy($url, $outputPath);
        }

        return $outputPath;
    }
}
