<?php

namespace App\YoutubeDownloader;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Process\Factory;
use Ramsey\Uuid\Uuid;

class Client {
    public function __construct(
        private readonly Application $app,
        private readonly Factory $processFactory,
    ) {}

    private function getVendorBinPath(): string {
        return $this->app->basePath('vendor/bin');
    }

    public function downloadAudio(string $url): string {
        $filename = Uuid::uuid4()->toString();

        $outputPath = sys_get_temp_dir()."/$filename.mp3";

        // todo: how to get metadata?

        $this->processFactory
            ->newPendingProcess()
            ->path($this->getVendorBinPath())
            ->run("./youtube-dl -x --audio-format=mp3 --audio-quality=2 -o $outputPath $url")
            ->throw();

        return $outputPath;
    }
}
