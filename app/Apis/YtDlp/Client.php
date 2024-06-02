<?php

namespace App\Apis\YtDlp;

use App\Enums\PlatformType;
use Carbon\CarbonInterval;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Process\Factory;
use Illuminate\Contracts\Process\ProcessResult;
use Ramsey\Uuid\Uuid;

/**
 * From GitHub: "yt-dlp is a feature-rich command-line audio/video downloader with support for thousands of sites. The
 * project is a fork of youtube-dl based on the now inactive youtube-dlc."
 */
readonly class Client {
    const METADATA_TIMEOUT = 30;
    const DOWNLOAD_TIMEOUT = 1200;

    public function __construct(
        private Application $app,
        private Repository  $cache,
        private Factory     $processFactory,
    ) {}

    private function getVendorBinPath(): string {
        return $this->app->basePath('vendor/bin');
    }

    private function run(int $timeout, array $args): ProcessResult {
        return $this->processFactory
            ->newPendingProcess()
            ->timeout($timeout)
            ->path($this->getVendorBinPath())
            ->run(array_merge(['./yt-dlp'], $args))
            ->throw();
    }

    private function getCacheKey(PlatformType $platformType, string $id): string {
        return "$platformType->value:$id";
    }

    private function cacheMetadata(PlatformType $platformType, string $id, array $metadata): void {
        $this->cache->put($this->getCacheKey($platformType, $id), $metadata, CarbonInterval::day());
    }

    private function getCachedMetadata(PlatformType $platformType, string $id): ?array {
        return $this->cache->get($this->getCacheKey($platformType, $id));
    }

    /**
     * @throws DownloadException
     */
    public function getMetadata(string $url): array {
        // Return cached metadata if available.
        if (!is_null($cached = $this->getCachedMetadata(PlatformType::YouTube, $url))) {
            return $cached;
        }

        try {
            // Run process and convert output to JSON.
            $jsonString = $this->run(self::METADATA_TIMEOUT, ['--dump-json', $url])->output();
        } catch (\Throwable $t) {
            // Wrap the exception, so we don't expose the command line process to the user.
            throw new DownloadException('Error downloading metadata from YouTube', previous: $t);
        }

        // Cache metadata before returning.
        return tap(
            json_decode($jsonString, true),
            fn(array $metadata) => $this->cacheMetadata(PlatformType::YouTube, $url, $metadata),
        );
    }

    public function downloadAudio(string $url): string {
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
