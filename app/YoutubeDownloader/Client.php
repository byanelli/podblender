<?php

namespace App\YoutubeDownloader;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Process\Factory;
use Illuminate\Contracts\Process\ProcessResult;
use Ramsey\Uuid\Uuid;

class Client {
    public function __construct(
        private readonly Application $app,
        private readonly Factory $processFactory,
    ) {}

    private function getVendorBinPath(): string {
        return $this->app->basePath('vendor/bin');
    }

    private function run(array $args): ProcessResult {
        return $this->processFactory
            ->newPendingProcess()
            ->path($this->getVendorBinPath())
            ->run("./youtube-dl ".join(' ', $args))
            ->throw();
    }

    public function getMetadata(string $url): Metadata {
        $jsonString = $this->run(['--dump-json', $url])->output();

        $json = json_decode($jsonString, true);

        return new Metadata(
            id: (int) $json['id'],
            title: $json['title'],
            description: $json['description'],
            channel_id: $json['channel_id'],
            channel: $json['channel'],
            duration: (int) $json['duration']
        );
    }

    public function downloadAudio(string $url): string {
        $filename = Uuid::uuid4()->toString();

        $outputPath = sys_get_temp_dir()."/$filename.mp3";

        $this->run([
            '-x',
            '--audio-format=mp3',
            '--audio-quality=2',
            "-o $outputPath",
            $url,
        ]);

        return $outputPath;
    }
}
