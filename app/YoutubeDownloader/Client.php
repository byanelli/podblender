<?php

namespace App\YoutubeDownloader;

use App\Cache\Metadata as MetadataCache;
use App\Enums\Platform;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Process\Factory;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class Client {
    const METADATA_TIMEOUT = 30;
    const DOWNLOAD_TIMEOUT = 1200;

    public function __construct(
        private readonly Application   $app,
        private readonly MetadataCache $cache,
        private readonly Factory       $processFactory,
    ) {}

    private function getVendorBinPath(): string {
        return $this->app->basePath('vendor/bin');
    }

    private function run(int $timeout, array $args): ProcessResult {
        return $this->processFactory
            ->newPendingProcess()
            ->timeout($timeout)
            ->path($this->getVendorBinPath())
            ->run("./youtube-dl ".join(' ', $args))
            ->throw();
    }

    // todo: make this generic for different platforms
    public function getMetadata(string $url): Metadata {
        $this->validateUserInput($url);

        $id = $this->normalizeId($url);

        // Return cached metadata if available.
        if (!is_null($cached = $this->cache->get(Platform::YouTube, $id))) {
            $cached instanceof Metadata || throw new \RuntimeException('???');
            return $cached;
        }

        // Run process and convert output to JSON.
        $jsonString = $this->run(self::METADATA_TIMEOUT, ['--dump-json', $this->normalizeUrl($url)])->output();

        $json = json_decode($jsonString, true);

        // Build metadata object and cache before returning.
        return tap(
            new Metadata(
                id: $json['id'],
                title: $json['title'],
                description: $json['description'],
                channel_id: $json['channel_id'],
                channel: $json['channel'],
                duration: (int) $json['duration']
            ),
            fn(Metadata $m) => $this->cache->put(Platform::YouTube, $m->id, $m)
        );
    }

    private function normalizeId(string $url): string {
        // todo: explain
        // todo: make a real Youtube URL parser
        return str_replace(['https://www.youtube.com/watch?v=' /*todo: others*/], '', $url);
    }

    private function normalizeUrl(string $url): string {
        // Some "URLs" passed to this class are actually video ids. When an id begins with "-", youtube-dl sees it as a
        // command line option, not a video id, so we reformat it as a URL instead. An example of an id beginning with
        // "-": https://www.youtube.com/watch?v=-J_xL4IGhJA -- Note: also a great video :)
        return str_starts_with($url, '-')
            ? 'https://www.youtube.com/watch?v='.$url
            : $url;
    }

    private function validateUserInput(string $input): void {
        // Ensure input contains no shell special characters that could hijack the youtube-dl command.
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
