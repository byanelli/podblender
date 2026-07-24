<?php

namespace App\Apis\Ffmpeg;

use App\Apis\Ffmpeg\Contracts\Client as ClientContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\Factory;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Spatie\Regex\Regex;

readonly class Client implements ClientContract
{
    public function __construct(
        private Application $app,
        private Factory $processFactory,
    ) {}

    private function getVendorBinPath(): string
    {
        return $this->app->basePath('vendor/bin');
    }

    /**
     * @param  array<int, string>  $args
     */
    private function run(int $timeout, array $args): ProcessResult
    {
        return $this->processFactory
            ->newPendingProcess()
            ->timeout($timeout)
            ->path($this->getVendorBinPath())
            ->run(array_merge(['./ffmpeg'], $args));
    }

    /**
     * @param  array<int, string>  $args
     */
    private function runSuccessfully(int $timeout, array $args): ProcessResult
    {
        return $this->run($timeout, $args)->throw();
    }

    /**
     * @param  array<int, string>  $mp3s
     */
    public function combineMp3s(array $mp3s): string
    {
        if (count($mp3s) === 1) {
            return collect($mp3s)->firstOrFail();
        }

        $outputPath = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.mp3';

        $this->runSuccessfully(600 /* todo */, [
            '-i',
            'concat:'.collect($mp3s)->implode('|'),
            '-acodec',
            'copy',
            $outputPath,
        ]);

        return $outputPath;
    }

    /**
     * Encode a raw, headerless PCM file (signed 16-bit little-endian, mono) to
     * MP3. Gemini's TTS returns audio as bare PCM samples with no container, so
     * ffmpeg has to be told the format explicitly rather than sniffing it.
     */
    public function pcmToMp3(string $pcm, int $sampleRate): string
    {
        $outputPath = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.mp3';

        $this->runSuccessfully(600 /* todo */, [
            '-f',
            's16le',
            '-ar',
            (string) $sampleRate,
            '-ac',
            '1',
            '-i',
            $pcm,
            // ffmpeg's default MP3 encode is ~32 kb/s, which sounds terrible.
            // Encode at a floor of 128 kb/s instead.
            '-b:a',
            '128k',
            $outputPath,
        ]);

        return $outputPath;
    }

    private function parseDurationString(string $duration): int
    {
        $match = Regex::match('/(\d\d):(\d\d):(\d\d).(\d\d)/', $duration);

        $hours = (int) $match->group(1);
        $minutes = (int) $match->group(2);
        $seconds = (int) $match->group(3);
        // todo: milliseconds?

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }

    public function getDuration(string $path): int
    {
        // We use "run" instead of "runSuccessfully" and parse the error output because ffmpeg throws an error without
        // any decoder set. Here we're only interested in the metadata it prints at the end of its run.
        $result = $this->run(5, ['-i', $path]);

        foreach (explode("\n", $result->errorOutput()) as $line) {
            if (Str::contains($line, 'Duration:')) {
                $match = Regex::match('/.*Duration: ([\d:\.]+),.*/', $line);

                return $this->parseDurationString($match->group(1));
            }
        }

        throw new \RuntimeException("Couldn't parse duration from ffmpeg output");
    }
}
