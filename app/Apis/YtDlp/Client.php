<?php

namespace App\Apis\YtDlp;

use App\Proxies\Contracts\ProxyConfig;
use App\Proxies\Contracts\ResidentialProxyConfig;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\Factory;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * Downloads audio with yt-dlp.
 *
 * YouTube decides whether to serve a request based on three things, roughly in order of importance:
 *
 *   1. Whether the request carries a valid proof-of-origin token (see scripts/install-bgutil-pot.php).
 *   2. The reputation of the source IP. Datacenter ranges, which include every commercial VPN endpoint, are flagged
 *      regardless of the rest, so requests go directly from the host first and through a residential proxy second.
 *   3. Whether the requests arrive in a burst. See the pacing below and in App\Jobs\DownloadAndStoreAudioClip.
 *
 * An outright refusal ("Sign in to confirm you're not a bot") lasts for hours. The direct attempt is not retried, and
 * the block is cached so that later downloads go to the residential proxy until the cache entry expires.
 */
readonly class Client
{
    const int DOWNLOAD_TIMEOUT = 1800;

    /**
     * Seconds to wait between the individual requests yt-dlp makes while extracting a single video. Pacing between
     * videos is done by the job (App\Jobs\DownloadAndStoreAudioClip).
     */
    const string SLEEP_BETWEEN_REQUESTS = '1.5';

    /**
     * Cache key for the flag that YouTube is refusing this host's address.
     */
    const string DIRECT_BLOCKED_CACHE_KEY = 'yt-dlp:direct-blocked';

    /**
     * Phrases in yt-dlp's error output that indicate YouTube refused the source address. Matched case-insensitively.
     * yt-dlp writes the apostrophe as a curly one in some places and versions, so both forms are listed.
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
        #[Config('services.ytdlp.direct_block_minutes')] private int $directBlockMinutes,
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
     * Arguments for every call to yt-dlp. They matter only to the YouTube extractor and are harmless elsewhere.
     *
     * All three paths are absolute: yt-dlp otherwise looks for Deno and the token provider on the PATH, and the queue
     * worker's PATH may not include this project's directories.
     *
     * @return array<int, string>
     */
    private function getCommonArgs(): array
    {
        return [
            // Without an external JavaScript runtime, yt-dlp can't solve YouTube's challenges and silently falls back
            // to a limited set of formats.
            "--js-runtimes=deno:{$this->getVendoredPath('bin/deno')}",

            // Load the proof-of-origin token provider plugin and give it the path to its executable.
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
     * Requests a proxy URL on each call, which is once per download attempt. With a rotating pool this gives one
     * address per download; a download fails if the address changes between its requests.
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
     * Whether YouTube refused the request because of its source address. "Confirm your age" is a different refusal
     * and must not match here.
     */
    private function downloadFailedDueToBotWall(ProcessResult $result): bool
    {
        return Str::contains($result->errorOutput(), self::BOT_WALL_MARKERS, ignoreCase: true);
    }

    /**
     * Download the audio at $url, optionally through a proxy. Without a proxy the request goes to YouTube directly
     * from this host.
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
     * $retryOnBotWall is false for the direct attempt and true for the proxied one. A bot wall applies to the source
     * address, so a retry from this host's single address will fail again, while a rotating residential pool uses a
     * new address per attempt.
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
     * Whether YouTube is known to be refusing this host's address. The flag expires, so a block that outlasts it costs
     * one failed direct attempt.
     */
    private function directDownloadsAreBlocked(): bool
    {
        return (bool) $this->cache->get(self::DIRECT_BLOCKED_CACHE_KEY, false);
    }

    private function rememberDirectDownloadsAreBlocked(): void
    {
        $this->cache->put(self::DIRECT_BLOCKED_CACHE_KEY, true, now()->addMinutes($this->directBlockMinutes));
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

            // No proxy to fall back to, and a direct attempt is known to fail, so skip the yt-dlp run.
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
            $this->retryWithExponentialBackoff(
                fn () => $this->runDownload($url, $outputPath),
                retryOnBotWall: false,
            );

            $this->logger->info("Successfully downloaded $url directly");
        } catch (BotWallException $e) {
            // Cache the refusal whether or not a proxy is configured. It lasts for hours.
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
            // The proxy is optional. Without one, rethrow the direct failure.
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
