<?php

namespace App\Apis\YtDlp;

use App\Proxies\Contracts\ProxyConfig;
use App\Proxies\Contracts\ResidentialProxyConfig;
use App\Proxies\Contracts\VpnProxyConfig;
use Carbon\CarbonInterval;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\Factory;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * From GitHub: "yt-dlp is a feature-rich command-line audio/video downloader with support for thousands of sites. The
 * project is a fork of youtube-dl based on the now inactive youtube-dlc."
 */
readonly class Client
{
    const int METADATA_TIMEOUT = 30;

    const int DOWNLOAD_TIMEOUT = 1800;

    public function __construct(
        private Application            $app,
        private LoggerInterface        $logger,
        private Repository             $cache,
        private Factory                $processFactory,
        private VpnProxyConfig         $vpnProxy,
        private ResidentialProxyConfig $residentialProxy,
    ) {}

    private function getVendorBinPath(): string
    {
        return $this->app->basePath('vendor/bin');
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

    private function getCacheKey(string $id): string
    {
        return "metadata:$id";
    }

    private function cacheMetadata(string $id, array $metadata): void
    {
        $this->cache->put($this->getCacheKey($id), $metadata, CarbonInterval::day());
    }

    private function getCachedMetadata(string $id): ?array
    {
        return $this->cache->get($this->getCacheKey($id));
    }

    /**
     * @throws ProcessFailedException
     */
    public function getMetadata(string $url): array
    {
        // Return cached metadata if available.
        if (! is_null($cached = $this->getCachedMetadata($url))) {
            return $cached;
        }

        // Run process and convert output to JSON.
        $jsonString = $this->run(self::METADATA_TIMEOUT, ['--dump-json', $url])->output();

        // Cache metadata before returning.
        return tap(
            json_decode($jsonString, true),
            fn (array $metadata) => $this->cacheMetadata($url, $metadata),
        );
    }

    private function runDownloadWithoutProxy(string $url, string $outputPath): ProcessResult
    {
        // todo match function below
        return $this->run(self::DOWNLOAD_TIMEOUT, [
            '-x',
            '--audio-format=mp3',
            '--audio-quality=2',
            '-o', $outputPath,
            $url,
        ]);
    }

    private function downloadFailedDueToMembersOnlyContent(ProcessResult $result): bool
    {
        // todo: more accurate detection?
        return str_contains($result->errorOutput(), 'members-only');
    }

    /**
     * @throws ProcessFailedException
     * @throws MembersOnlyContentException
     */
    private function runDownloadWithProxy(string $url, string $outputPath, ProxyConfig $proxy): ProcessResult
    {
        try {
            // Double the download timeout because a proxy may be slower.
            return $this->run(self::DOWNLOAD_TIMEOUT * 2, [
                "--proxy={$proxy->getProtocol()}://{$proxy->getUser()}:{$proxy->getPassword()}@{$proxy->getHost()}:{$proxy->getPort()}",
                '--extract-audio',
                '--no-check-certificates', // todo: make configurable per-proxy
                '--impersonate=Chrome-120', // todo: random?
                '--audio-format=mp3',
                '--audio-quality=2',
                '-o', $outputPath,
                $url,
            ]);
        } catch (ProcessFailedException $e) {
            if ($this->downloadFailedDueToMembersOnlyContent($e->result)) {
                $this->logger->error("Couldn't download $url because it's a members-only video");

                throw new MembersOnlyContentException();
            } else {
                throw $e;
            }
        }
    }

    /**
     * @throws ProcessFailedException
     * @throws MembersOnlyContentException
     */
    private function retryWithExponentialBackoff(callable $callback, int $retryTimes=3, int $baseSleepSeconds=60): mixed
    {
        return retry(
            times: $retryTimes,
            callback: $callback,
            sleepMilliseconds: fn (int $attempts) => $baseSleepSeconds * pow(2, $attempts-1) * 1000,
            // No point in retrying if the content is members-only.
            when: fn (\Throwable $t) => !($t instanceof MembersOnlyContentException)
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
            // todo: multiple VPNs?

            // Try to run the download using exponential backoff with a VPN proxy.
            $this->retryWithExponentialBackoff(
                fn() => $this->runDownloadWithProxy($url, $outputPath, $this->vpnProxy)
            );

            $this->logger->info("Successfully downloaded $url with VPN proxy");
        } catch (ProcessFailedException $e) {
            $this->logger->warning("Failed to download $url with VPN proxy; trying residential proxy");

            try {
                // Try to run the download using exponential backoff with a residential proxy.
                $this->retryWithExponentialBackoff(
                    fn() => $this->runDownloadWithProxy($url, $outputPath, $this->residentialProxy)
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
