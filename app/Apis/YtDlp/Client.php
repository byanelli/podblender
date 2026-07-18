<?php

namespace App\Apis\YtDlp;

use App\Proxies\Contracts\ProxyConfig;
use App\Proxies\Contracts\ResidentialProxyConfig;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\Factory;
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
 */
readonly class Client
{
    const int DOWNLOAD_TIMEOUT = 1800;

    /**
     * Seconds to wait between the individual requests yt-dlp makes while extracting a single video. Pacing *between*
     * videos is the job's responsibility, not ours: it's the layer that knows what else is queued up.
     */
    const string SLEEP_BETWEEN_REQUESTS = '1.5';

    public function __construct(
        private Application $app,
        private LoggerInterface $logger,
        private Factory $processFactory,
        private ResidentialProxyConfig $residentialProxy,
    ) {}

    private function getVendorBinPath(): string
    {
        return $this->app->basePath('vendor/bin');
    }

    private function getVendoredPath(string $path): string
    {
        return $this->app->basePath("vendor/$path");
    }

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
     * Download the audio at $url, optionally by way of a proxy. Passing no proxy means talking to YouTube directly
     * from this host, which is the preferable case: a residential connection is the most credible address we have.
     *
     * @throws ProcessFailedException
     * @throws MembersOnlyContentException
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
            } else {
                throw $e;
            }
        }
    }

    /**
     * @throws ProcessFailedException
     * @throws MembersOnlyContentException
     */
    private function retryWithExponentialBackoff(callable $callback, int $retryTimes = 3, int $baseSleepSeconds = 60): mixed
    {
        return retry(
            times: $retryTimes,
            callback: $callback,
            sleepMilliseconds: fn (int $attempts) => $baseSleepSeconds * pow(2, $attempts - 1) * 1000,
            // No point in retrying if the content is members-only.
            when: fn (\Throwable $t) => ! ($t instanceof MembersOnlyContentException)
        );
    }

    /**
     * @throws ProcessFailedException
     * @throws MembersOnlyContentException
     */
    public function downloadAudio(string $url): string
    {
        $filename = Uuid::uuid4()->toString();

        $outputPath = sys_get_temp_dir()."/$filename.mp3";

        try {
            // Try to run the download using exponential backoff directly from this host.
            $this->retryWithExponentialBackoff(
                fn () => $this->runDownload($url, $outputPath)
            );

            $this->logger->info("Successfully downloaded $url directly");
        } catch (ProcessFailedException $e) {
            $this->logger->warning("Failed to download $url directly; trying residential proxy");

            try {
                // Try to run the download using exponential backoff with a residential proxy.
                $this->retryWithExponentialBackoff(
                    fn () => $this->runDownload($url, $outputPath, $this->residentialProxy)
                );

                $this->logger->info("Successfully downloaded $url with residential proxy");
            } catch (ProcessFailedException $e) {
                $this->logger->error("Failed to download $url with residential proxy; giving up");

                throw $e;
            }
        }

        return $outputPath;
    }
}
