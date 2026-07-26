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
            // -y: overwrite the output without asking. Every output path here is
            // a UUID we just generated, so there's nothing to protect — and if
            // one does exist, ffmpeg's default is to prompt, read EOF from our
            // non-interactive stdin, and exit *successfully* having written
            // nothing, leaving whatever was already there.
            ->run(array_merge(['./ffmpeg', '-y'], $args));
    }

    /**
     * @param  array<int, string>  $args
     */
    private function runSuccessfully(int $timeout, array $args): ProcessResult
    {
        return $this->run($timeout, $args)->throw();
    }

    /**
     * Run ffmpeg and insist it actually wrote audio to $outputPath.
     *
     * ffmpeg has been observed to exit 0 having written an empty file, which is
     * worse than an error: an empty MP3 is silently concatenated into the
     * finished episode as a stretch of nothing, or stored as a clip that plays
     * for zero seconds. Treat it as the failure it is, so the job retries.
     *
     * @param  array<int, string>  $args
     */
    private function runProducingAudio(int $timeout, array $args, string $outputPath): void
    {
        $result = $this->runSuccessfully($timeout, $args);

        clearstatcache(true, $outputPath);

        if (! file_exists($outputPath) || filesize($outputPath) === 0) {
            // Include the whole of ffmpeg's output. It's only a few kilobytes,
            // this is rare and awkward to reproduce, and the log line is the
            // only evidence we'll get of why it happened — so don't trim it.
            throw new \RuntimeException(
                "ffmpeg exited successfully but wrote no audio to $outputPath.\n".
                'Command: '.implode(' ', $args)."\n".
                'Output: '.trim($result->errorOutput())
            );
        }
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

        $this->runProducingAudio(600 /* todo */, [
            '-i',
            'concat:'.collect($mp3s)->implode('|'),
            '-acodec',
            'copy',
            $outputPath,
        ], $outputPath);

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

        $this->runProducingAudio(600 /* todo */, [
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
        ], $outputPath);

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
