<?php

namespace App\Apis\Ffmpeg;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\Factory;
use Ramsey\Uuid\Uuid;

readonly class Client
{
    public function __construct(
        private Application $app,
        private Factory $processFactory
    ) {}

    private function getVendorBinPath(): string {
        return $this->app->basePath('vendor/bin');
    }

    private function run(int $timeout, array $args): ProcessResult {
        return $this->processFactory
            ->newPendingProcess()
            ->timeout($timeout)
            ->path($this->getVendorBinPath())
            ->run(array_merge(['./ffmpeg'], $args))
            ->throw();
    }

    public function combineMp3s(array $mp3s): string {
        if (count($mp3s) === 1) {
            return collect($mp3s)->firstOrFail();
        }

        $outputPath = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.mp3';

        $this->run(600 /*todo*/, [
            '-i',
            'concat:'.collect($mp3s)->implode('|'),
            '-acodec',
            'copy',
            $outputPath
        ]);

        return $outputPath;
    }
}
