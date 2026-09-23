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
 * Downloads audio and reads metadata with yt-dlp. Each call takes the Site it's for, which provides the site's format
 * selector and error phrases.
 *
 * The notes below are about YouTube, the site most likely to refuse this host. YouTube decides whether to serve a
 * request based on three things, roughly in order of importance:
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
     * Seconds allowed for a query of one item's metadata. The caller may be a web request.
     */
    const int INFO_TIMEOUT = 120;

    /**
     * Seconds allowed for listing a source's items, which fetches each item. The subscription job's timeout is 1800.
     */
    const int ENTRIES_TIMEOUT = 1500;

    /**
     * yt-dlp's exit code when --break-match-filters stopped a playlist early.
     */
    const int EXIT_CODE_BROKE_OFF_PLAYLIST = 101;

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
     * Cache key for the flag that the site is refusing this host's address.
     */
    public static function directBlockedCacheKey(Site $site): string
    {
        return "yt-dlp:direct-blocked:{$site->name}";
    }

    /**
     * @param  array<int, string>  $args
     * @param  array<int, int>  $successExitCodes
     *
     * @throws ProcessFailedException
     */
    private function run(int $timeout, array $args, array $successExitCodes = [0]): ProcessResult
    {
        $result = $this->processFactory
            ->newPendingProcess()
            ->timeout($timeout)
            ->path($this->getVendorBinPath())
            ->run(array_merge(['./yt-dlp'], $args));

        if (! in_array($result->exitCode(), $successExitCodes, true)) {
            throw new ProcessFailedException($result);
        }

        return $result;
    }

    /**
     * Arguments for every call to yt-dlp. The paths matter only to the YouTube extractor and are harmless elsewhere.
     *
     * All three paths are absolute: yt-dlp otherwise looks for Deno and the token provider on the PATH, and the queue
     * worker's PATH may not include this project's directories.
     *
     * @return array<int, string>
     */
    private function getCommonArgs(Site $site): array
    {
        return array_filter([
            // Without an external JavaScript runtime, yt-dlp can't solve YouTube's challenges and silently falls back
            // to a limited set of formats.
            "--js-runtimes=deno:{$this->getVendoredPath('bin/deno')}",

            // Load the proof-of-origin token provider plugin and give it the path to its executable.
            "--plugin-dirs={$this->getVendoredPath('yt-dlp-plugins')}",
            "--extractor-args=youtubepot-bgutilcli:cli_path={$this->getVendoredPath('bin/bgutil-pot')}",

            is_null($site->secondsBetweenRequests) ? null : "--sleep-requests=$site->secondsBetweenRequests",
        ]);
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

            // Only for proxies that can't leave TLS end-to-end. Never passed when talking to the site directly.
            $proxy->requiresInsecureTls() ? '--no-check-certificates' : null,
        ]);
    }

    /**
     * Run yt-dlp and classify a failure by the site's error phrases.
     *
     * @param  array<int, string>  $args
     * @param  array<int, int>  $successExitCodes
     *
     * @throws ProcessFailedException
     * @throws UnavailableContentException
     * @throws BotWallException
     */
    private function runForSite(
        Site $site,
        string $url,
        int $timeout,
        array $args,
        ?ProxyConfig $proxy = null,
        array $successExitCodes = [0],
    ): ProcessResult {
        try {
            return $this->run(
                // Double the timeout when proxied, because a proxy may be slower.
                is_null($proxy) ? $timeout : $timeout * 2,
                array_merge(
                    $this->getCommonArgs($site),
                    is_null($proxy) ? [] : $this->getProxyArgs($proxy),
                    $args,
                    [$url],
                ),
                $successExitCodes,
            );
        } catch (ProcessFailedException $e) {
            $errorOutput = $e->result->errorOutput();

            if ($site->unavailableMarkers !== [] && Str::contains($errorOutput, $site->unavailableMarkers, ignoreCase: true)) {
                $this->logger->error("Couldn't get $url from $site->name because the content is unavailable");

                throw new UnavailableContentException($errorOutput, previous: $e);
            } elseif ($site->botWallMarkers !== [] && Str::contains($errorOutput, $site->botWallMarkers, ignoreCase: true)) {
                $this->logger->warning("Couldn't get $url because $site->name refused the address it came from");

                throw new BotWallException($e->result);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Download the audio at $url, optionally through a proxy. Without a proxy the request goes to the site directly
     * from this host.
     *
     * @throws ProcessFailedException
     * @throws UnavailableContentException
     * @throws BotWallException
     */
    private function runDownload(Site $site, string $url, string $outputPath, ?ProxyConfig $proxy = null): ProcessResult
    {
        return $this->runForSite(
            $site,
            $url,
            self::DOWNLOAD_TIMEOUT,
            array_merge(
                is_null($site->format) ? [] : ['--format', $site->format],
                $this->getAudioArgs($outputPath),
            ),
            $proxy,
        );
    }

    /**
     * $retryOnBotWall is false for the direct attempt and true for the proxied one. A bot wall applies to the source
     * address, so a retry from this host's single address will fail again, while a rotating residential pool uses a
     * new address per attempt.
     *
     * @throws ProcessFailedException
     * @throws UnavailableContentException
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
                $t instanceof UnavailableContentException => false,
                $t instanceof BotWallException            => $retryOnBotWall,
                default                                   => true,
            },
        );
    }

    /**
     * Whether the site is known to be refusing this host's address. The flag expires, so a block that outlasts it
     * costs one failed direct attempt.
     */
    private function directRequestsAreBlocked(Site $site): bool
    {
        return (bool) $this->cache->get(self::directBlockedCacheKey($site), false);
    }

    private function rememberDirectRequestsAreBlocked(Site $site): void
    {
        $this->cache->put(self::directBlockedCacheKey($site), true, now()->addMinutes($this->directBlockMinutes));
    }

    /**
     * @throws ProcessFailedException
     * @throws UnavailableContentException
     * @throws BotWallException
     */
    private function downloadThroughResidentialProxy(Site $site, string $url, string $outputPath): void
    {
        try {
            $this->retryWithExponentialBackoff(
                fn () => $this->runDownload($site, $url, $outputPath, $this->residentialProxy)
            );

            $this->logger->info("Successfully downloaded $url with residential proxy");
        } catch (ProcessFailedException $e) {
            $this->logger->error("Failed to download $url with residential proxy; giving up");

            throw $e;
        }
    }

    /**
     * @throws ProcessFailedException
     * @throws UnavailableContentException
     * @throws BotWallException
     */
    public function downloadAudio(string $url, Site $site): string
    {
        $filename = Uuid::uuid4()->toString();

        $outputPath = sys_get_temp_dir()."/$filename.mp3";

        if ($this->directRequestsAreBlocked($site)) {
            $this->logger->info(
                "Not downloading $url directly: $site->name is refusing this host's address until the block expires"
            );

            // No proxy to fall back to, and a direct attempt is known to fail, so skip the yt-dlp run.
            if (! $this->residentialProxy->isConfigured()) {
                $this->logger->error(
                    "Can't download $url: $site->name is refusing this host's address and no residential proxy is "
                    .'configured to fall back to'
                );

                throw BotWallException::withoutRunningYtDlp($url, $site);
            }

            $this->downloadThroughResidentialProxy($site, $url, $outputPath);

            return $outputPath;
        }

        try {
            $this->retryWithExponentialBackoff(
                fn () => $this->runDownload($site, $url, $outputPath),
                retryOnBotWall: false,
            );

            $this->logger->info("Successfully downloaded $url directly");
        } catch (BotWallException $e) {
            // Cache the refusal whether or not a proxy is configured. It lasts for hours.
            $this->rememberDirectRequestsAreBlocked($site);

            if (! $this->residentialProxy->isConfigured()) {
                $this->logger->error(
                    "Failed to download $url: $site->name is refusing this host's address and no residential proxy is "
                    .'configured to fall back to'
                );

                throw $e;
            }

            $this->logger->warning(
                "$site->name is refusing this host's address; downloading $url through the residential proxy instead"
            );

            $this->downloadThroughResidentialProxy($site, $url, $outputPath);
        } catch (ProcessFailedException $e) {
            // The proxy is optional. Without one, rethrow the direct failure.
            if (! $this->residentialProxy->isConfigured()) {
                $this->logger->error(
                    "Failed to download $url directly, and no residential proxy is configured to fall back to"
                );

                throw $e;
            }

            $this->logger->warning("Failed to download $url directly; trying residential proxy");

            $this->downloadThroughResidentialProxy($site, $url, $outputPath);
        }

        return $outputPath;
    }

    /**
     * The info JSON for $url. $args select what to extract, e.g. --flat-playlist for a playlist without its items.
     *
     * @param  array<int, string>  $args
     *
     * @throws ProcessFailedException
     * @throws UnavailableContentException
     * @throws BotWallException
     */
    public function getInfo(string $url, Site $site, array $args = []): Info
    {
        $result = $this->runQuery($site, $url, self::INFO_TIMEOUT, array_merge(['--dump-single-json'], $args));

        return Info::fromJson($this->decodeJson($result->output()));
    }

    /**
     * The info JSON for each item of the playlist at $url, one yt-dlp line per item. $args may stop the listing early
     * with --break-match-filters.
     *
     * @param  array<int, string>  $args
     * @return array<int, Info>
     *
     * @throws ProcessFailedException
     * @throws UnavailableContentException
     * @throws BotWallException
     */
    public function getEntries(string $url, Site $site, array $args = []): array
    {
        $result = $this->runQuery(
            $site,
            $url,
            self::ENTRIES_TIMEOUT,
            array_merge(['--dump-json'], $args),
            successExitCodes: [0, self::EXIT_CODE_BROKE_OFF_PLAYLIST],
        );

        return collect(explode("\n", $result->output()))
            ->filter(fn (string $line) => trim($line) !== '')
            ->map(fn (string $line) => Info::fromJson($this->decodeJson($line)))
            ->values()
            ->all();
    }

    /**
     * Runs a query directly, or through the residential proxy while the site is known to be refusing this host. A
     * query isn't retried, since the caller may be a web request, but a new bot wall is remembered and the query is
     * repeated once through the proxy.
     *
     * @param  array<int, string>  $args
     * @param  array<int, int>  $successExitCodes
     *
     * @throws ProcessFailedException
     * @throws UnavailableContentException
     * @throws BotWallException
     */
    private function runQuery(Site $site, string $url, int $timeout, array $args, array $successExitCodes = [0]): ProcessResult
    {
        $proxied = fn () => $this->runForSite($site, $url, $timeout, $args, $this->residentialProxy, $successExitCodes);

        if ($this->directRequestsAreBlocked($site) && $this->residentialProxy->isConfigured()) {
            return $proxied();
        }

        try {
            return $this->runForSite($site, $url, $timeout, $args, successExitCodes: $successExitCodes);
        } catch (BotWallException $e) {
            $this->rememberDirectRequestsAreBlocked($site);

            if (! $this->residentialProxy->isConfigured()) {
                throw $e;
            }

            return $proxied();
        }
    }

    /**
     * @return array<mixed>
     */
    private function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new \UnexpectedValueException('yt-dlp printed JSON that is not an object');
        }

        return $decoded;
    }
}
