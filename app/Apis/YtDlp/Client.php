<?php

namespace App\Apis\YtDlp;

use Carbon\CarbonInterval;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\Factory;
use Ramsey\Uuid\Uuid;

/**
 * From GitHub: "yt-dlp is a feature-rich command-line audio/video downloader with support for thousands of sites. The
 * project is a fork of youtube-dl based on the now inactive youtube-dlc."
 */
readonly class Client
{
    const int METADATA_TIMEOUT = 30;

    const int DOWNLOAD_TIMEOUT = 1200;

    public function __construct(
        private Application $app,
        private Repository $cache,
        private Factory $processFactory,
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

    /**
     * @throws ProcessFailedException
     */
    public function downloadAudio(string $url): string
    {
        $filename = Uuid::uuid4()->toString();

        $outputPath = sys_get_temp_dir()."/$filename.mp3";

        $this->run(self::DOWNLOAD_TIMEOUT, [
            '-x',
            '--audio-format=mp3',
            '--audio-quality=2',
            '-o', $outputPath,
            $url,
        ]);

        return $outputPath;
    }
}
