<?php

namespace App\Apis\Ffmpeg;

use App\Apis\Ffmpeg\Contracts\Client as ClientContract;
use App\Platforms\Contracts\Chapter;
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
            // -y: overwrite the output without asking. Without it, if the output
            // exists, ffmpeg prompts, reads EOF from the non-interactive stdin,
            // and exits 0 having written nothing.
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
     * Run ffmpeg and throw unless it wrote audio to $outputPath.
     *
     * ffmpeg has been observed to exit 0 having written nothing. Undetected,
     * that leaves a segment out of the finished episode or stores a clip that
     * plays for zero seconds. Throwing makes the job retry.
     *
     * A non-empty file isn't sufficient, because encoding no samples still
     * produces a valid header-only MP3, so the check is on the duration ffmpeg
     * reports for the output.
     *
     * @param  array<int, string>  $args
     */
    private function runProducingAudio(int $timeout, array $args, string $outputPath): void
    {
        $result = $this->runSuccessfully($timeout, $args);

        clearstatcache(true, $outputPath);

        $duration = (file_exists($outputPath) && filesize($outputPath) > 0)
            ? $this->getPreciseDurationOrNull($outputPath)
            : null;

        if (($duration ?? 0.0) <= 0.0) {
            // Include all of ffmpeg's output: it's a few kilobytes, the failure
            // is rare and hard to reproduce, and the log is the only record.
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
     * MP3. Gemini's TTS returns bare PCM samples with no container, so the
     * input format is passed to ffmpeg explicitly.
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
            '-b:a',
            '128k',
            // combineMp3s() concatenates these segments byte-wise, so a
            // Xing/LAME header or ID3 tag would end up mid-file, where decoders
            // report a missing header and skip it. Omit both; ffmpeg writes
            // them for the combined file.
            '-write_xing',
            '0',
            '-id3v2_version',
            '0',
            $outputPath,
        ], $outputPath);

        return $outputPath;
    }

    public function addChapters(string $mp3, array $chapters, int $durationSeconds): string
    {
        $outputPath = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.mp3';
        $metadataPath = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.txt';

        file_put_contents($metadataPath, $this->chapterMetadata($chapters, $durationSeconds));

        try {
            $this->runProducingAudio(300, [
                '-i',
                $mp3,
                '-i',
                $metadataPath,
                '-map',
                '0',
                '-map_metadata',
                '0',
                '-map_chapters',
                '1',
                '-c',
                'copy',
                // ID3v2.3 is the version podcast apps read most reliably. ffmpeg writes v2.4 by default.
                '-id3v2_version',
                '3',
                $outputPath,
            ], $outputPath);
        } finally {
            @unlink($metadataPath);
        }

        return $outputPath;
    }

    /**
     * Chapters in ffmpeg's FFMETADATA1 format. Each chapter ends where the next starts, and the last at the end of the
     * audio. In a value, "=", ";", "#", "\" and newlines must be escaped with a backslash.
     *
     * @param  list<Chapter>  $chapters
     */
    private function chapterMetadata(array $chapters, int $durationSeconds): string
    {
        $chapters = array_values(array_filter($chapters, fn (Chapter $c) => $c->startSeconds < $durationSeconds));

        $metadata = ";FFMETADATA1\n";

        foreach ($chapters as $i => $chapter) {
            $end = $chapters[$i + 1]->startSeconds ?? $durationSeconds;
            $title = preg_replace('/([=;#\\\\\n])/', '\\\\$1', $chapter->title);

            $metadata .= "[CHAPTER]\nTIMEBASE=1/1\nSTART={$chapter->startSeconds}\nEND=$end\ntitle=$title\n";
        }

        return $metadata;
    }

    /**
     * Remove an image's black bars, crop it to a centered square, and re-encode
     * it as a JPEG $maxSide on a side. Returns the path to the new file.
     *
     * Smaller images are enlarged: Apple Podcasts requires artwork between 1400
     * and 3000 pixels square, and YouTube's largest thumbnail is 1280x720.
     * Lanczos enlarges more sharply than ffmpeg's default bicubic.
     *
     * -frames:v 1 takes the first frame of an animated input. An alpha channel is
     * dropped in the conversion to JPEG.
     */
    public function imageToSquareJpeg(string $inputPath, int $maxSide = 1400): string
    {
        $outputPath = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.jpg';

        $barsCrop = $this->detectBlackBars($inputPath);

        $this->runProducingFile(120, [
            '-i',
            $inputPath,
            '-vf',
            ($barsCrop === null ? '' : "$barsCrop,")
                ."crop='min(iw,ih)':'min(iw,ih)',scale=$maxSide:$maxSide:flags=lanczos",
            '-frames:v',
            '1',
            // 2 is ffmpeg's near-best JPEG quality. Artwork is displayed at a
            // few hundred pixels, so 1 isn't worth the extra bytes.
            '-q:v',
            '2',
            $outputPath,
        ], $outputPath);

        return $outputPath;
    }

    /**
     * A crop filter that removes an image's black bars, or null if it has none.
     *
     * YouTube's hqdefault thumbnail is 4:3 with a 16:9 frame letterboxed inside
     * it, and a portrait video's thumbnail is pillarboxed. A detected area under
     * a third of either side is taken to be a mostly dark image and ignored.
     */
    private function detectBlackBars(string $inputPath): ?string
    {
        // skip=0: cropdetect skips the first two frames by default, and an image has one.
        $output = $this->run(30, [
            '-i',
            $inputPath,
            '-vf',
            'cropdetect=limit=24:round=2:skip=0',
            '-f',
            'null',
            '-',
        ])->errorOutput();

        if (! preg_match('/Video: .*?(\d{2,})x(\d{2,})/', $output, $size)
            || ! preg_match_all('/crop=(\d+):(\d+):\d+:\d+/', $output, $crops, PREG_SET_ORDER)
        ) {
            return null;
        }

        $crop = $crops[array_key_last($crops)];
        [$width, $height] = [(int) $size[1], (int) $size[2]];
        [$cropWidth, $cropHeight] = [(int) $crop[1], (int) $crop[2]];

        if (($cropWidth === $width && $cropHeight === $height)
            || $cropWidth * 3 < $width
            || $cropHeight * 3 < $height
        ) {
            return null;
        }

        return $crop[0];
    }

    /**
     * Run ffmpeg and throw unless it wrote a non-empty file to $outputPath.
     *
     * Handles the same failure as runProducingAudio() (exit 0, nothing
     * written), without the duration check, which doesn't apply to an image.
     *
     * @param  array<int, string>  $args
     */
    private function runProducingFile(int $timeout, array $args, string $outputPath): void
    {
        $result = $this->runSuccessfully($timeout, $args);

        clearstatcache(true, $outputPath);

        if (! file_exists($outputPath) || filesize($outputPath) === 0) {
            throw new \RuntimeException(
                "ffmpeg exited successfully but wrote no file to $outputPath.\n".
                'Command: '.implode(' ', $args)."\n".
                'Output: '.trim($result->errorOutput())
            );
        }
    }

    public function getDuration(string $path): int
    {
        $duration = $this->getPreciseDurationOrNull($path);

        if ($duration === null) {
            throw new \RuntimeException("Couldn't parse duration from ffmpeg output");
        }

        return (int) $duration;
    }

    /**
     * The duration ffmpeg reports for a file, in seconds, or null if it didn't
     * report one (an unreadable or audio-less file).
     *
     * Keeps the fractional part that getDuration() truncates, because the
     * post-encode check must accept a segment shorter than a second. Returns
     * null instead of throwing so that check needs no catch block.
     */
    private function getPreciseDurationOrNull(string $path): ?float
    {
        // Uses run(), not runSuccessfully(): with no output file ffmpeg exits with an error, but it still prints the
        // input's metadata to stderr, which is parsed below.
        $result = $this->run(5, ['-i', $path]);

        foreach (explode("\n", $result->errorOutput()) as $line) {
            if (Str::contains($line, 'Duration:')) {
                $match = Regex::match('/.*Duration: (\d\d):(\d\d):(\d\d[\d\.]*),.*/', $line);

                if (! $match->hasMatch()) {
                    continue;
                }

                return ((int) $match->group(1) * 3600)
                    + ((int) $match->group(2) * 60)
                    + (float) $match->group(3);
            }
        }

        return null;
    }
}
