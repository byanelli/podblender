<?php

namespace App\Apis\YtDlp;

use App\Enums\PlatformType;
use Carbon\CarbonInterval;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Process\Factory;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
            ->run("./yt-dlp ".join(' ', $args)) // todo: use array
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
    public function getMetadata(string $id): array {
        $this->validateUserInput($id);

        $this->ensureStringIsNotUrl($id);

        // Return cached metadata if available.
        if (!is_null($cached = $this->getCachedMetadata(PlatformType::YouTube, $id))) {
            return $cached;
        }

        try {
            // Run process and convert output to JSON.
            $jsonString = $this->run(self::METADATA_TIMEOUT, ['--dump-json', $this->normalizeUrl($id)])->output();
        } catch (\Throwable $t) {
            // Wrap the exception, so we don't expose the command line process to the user.
            throw new DownloadException('Error downloading metadata from YouTube', previous: $t);
        }

        // Cache metadata before returning.
        return tap(
            json_decode($jsonString, true),
            fn(array $metadata) => $this->cacheMetadata(PlatformType::YouTube, $id, $metadata),
        );
    }

    /**
     * When an id begins with "-", yt-dlp sees it as a command line option, not a video id, so we reformat it as a URL
     * instead. An example of an id beginning with "-": https://www.youtube.com/watch?v=-J_xL4IGhJA -- Note: also a
     * great video :)
     *
     * @param string $url
     * @return string
     */
    private function normalizeUrl(string $url): string {
        return str_starts_with($url, '-')
            ? 'https://www.youtube.com/watch?v='.$url
            : $url;
    }

    /**
     * Ensure the input contains no shell special characters that could hijack the yt-dlp command.
     *
     * @param string $input
     * @return void
     */
    private function validateUserInput(string $input): void {
        if (Str::of($input)->contains(['!', '@', '$', '&', '\\', '*', ' ', ';', '|', '%', '#'])) {
            Log::error("Possible shell injection attempt: $input");
            throw new \InvalidArgumentException('Invalid input');
        }
    }

    public function downloadAudio(string $id): string {
        $this->validateUserInput($id);

        $this->ensureStringIsNotUrl($id);

        $filename = Uuid::uuid4()->toString();

        $outputPath = sys_get_temp_dir()."/$filename.mp3";

        $this->run(self::DOWNLOAD_TIMEOUT, [
            '-x',
            '--audio-format=mp3',
            '--audio-quality=2',
            "-o $outputPath",
            $this->normalizeUrl($id),
        ]);

        return $outputPath;
    }

    /**
     * To avoid cache misses, we need to pass ids, *not* URLs, to this class. One id could correspond to many valid
     * URLs.
     *
     * @param string $id
     * @return void
     */
    private function ensureStringIsNotUrl(string $id): void {
        if (str_starts_with($id, 'http:') || str_starts_with($id, 'https:')) {
            throw new \RuntimeException('Expected an id, not a URL');
        }
    }
}
