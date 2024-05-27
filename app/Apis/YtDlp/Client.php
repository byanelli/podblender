<?php

namespace App\Apis\YtDlp;

use App\Enums\Platform;
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

    private function getCacheKey(Platform $platform, string $id): string {
        return "$platform->value:$id";
    }

    private function cacheMetadata(Platform $platform, string $id, array $metadata): void {
        $this->cache->put($this->getCacheKey($platform, $id), $metadata, CarbonInterval::day());
    }

    private function getCachedMetadata(Platform $platform, string $id): ?array {
        return $this->cache->get($this->getCacheKey($platform, $id));
    }

    /**
     * @throws DownloadException
     */
    public function getRawMetadata(string $url): array {
        $this->validateUserInput($url);

        $id = $this->normalizeId($url);

        // Return cached metadata if available.
        if (!is_null($cached = $this->getCachedMetadata(Platform::YouTube, $id))) {
            return $cached;
        }

        try {
            // Run process and convert output to JSON.
            $jsonString = $this->run(self::METADATA_TIMEOUT, ['--dump-json', $this->normalizeUrl($url)])->output();
        } catch (\Throwable $t) {
            // Wrap the exception so we don't expose the command line process to the user.
            throw new DownloadException('Error downloading metadata from YouTube', previous: $t);
        }

        // Cache metadata before returning.
        return tap(
            json_decode($jsonString, true),
            fn(array $metadata) => $this->cacheMetadata(Platform::YouTube, $id, $metadata),
        );
    }

    /**
     * todo: make this generic for different platforms
     * @throws DownloadException
     * @deprecated
     */
    public function getYoutubeMetadata(string $url): Metadata {
        $json = $this->getRawMetadata($url);

        return new Metadata(
            id: $json['id'],
            title: $json['title'],
            description: $json['description'],
            channel_id: $json['channel_id'],
            channel: $json['channel'],
            duration: (int)$json['duration']
        );
    }

    private function normalizeId(string $url): string {
        // todo: explain
        // todo: make a real Youtube URL parser
        return str_replace(['https://www.youtube.com/watch?v=' /*todo: others*/], '', $url);
    }

    private function normalizeUrl(string $url): string {
        // Some "URLs" passed to this class are actually video ids. When an id begins with "-", yt-dlp sees it as a
        // command line option, not a video id, so we reformat it as a URL instead. An example of an id beginning with
        // "-": https://www.youtube.com/watch?v=-J_xL4IGhJA -- Note: also a great video :)
        return str_starts_with($url, '-')
            ? 'https://www.youtube.com/watch?v='.$url
            : $url;
    }

    private function validateUserInput(string $input): void {
        // Ensure input contains no shell special characters that could hijack the yt-dlp command.
        if (Str::of($input)->contains(['!', '@', '$', '&', '\\', '*', ' ', ';', '|', '%', '#'])) {
            Log::error("Possible shell injection attempt: $input");
            throw new \InvalidArgumentException('Invalid input');
        }
    }

    public function downloadAudio(string $url): string {
        $this->validateUserInput($url);

        $filename = Uuid::uuid4()->toString();

        $outputPath = sys_get_temp_dir()."/$filename.mp3";

        $this->run(self::DOWNLOAD_TIMEOUT, [
            '-x',
            '--audio-format=mp3',
            '--audio-quality=2',
            "-o $outputPath",
            $this->normalizeUrl($url),
        ]);

        return $outputPath;
    }
}
