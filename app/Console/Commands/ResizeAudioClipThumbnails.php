<?php

namespace App\Console\Commands;

use App\Apis\Ffmpeg\Contracts\Client as Ffmpeg;
use App\Models\AudioClip;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Ramsey\Uuid\Uuid;

/**
 * Brings already-stored episode artwork up to the size Apple Podcasts expects.
 *
 * imageToSquareJpeg() used to leave an image smaller than 1400 pixels at the
 * size it arrived, so every thumbnail taken from a 1280x720 YouTube frame was
 * stored at 720x720 — under Apple's minimum, and liable to be dropped from the
 * feed. New clips are handled at download time; this is for the ones already
 * on disk.
 *
 * Each image is rewritten at the path it already occupies, so running this
 * twice is harmless: the second pass measures 1400x1400 and leaves it alone.
 */
class ResizeAudioClipThumbnails extends Command
{
    protected $signature = 'clips:resize-thumbnails {--dry-run : Report what would change without writing anything}';

    protected $description = 'Re-encode stored episode artwork at the 1400x1400 Apple Podcasts expects';

    /**
     * Apple's artwork minimum, and what imageToSquareJpeg() produces by default.
     */
    private const SIDE = 1400;

    public function handle(Filesystem $storage, Ffmpeg $ffmpeg): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('Dry run: nothing will be written.');
        }

        $resized = 0;
        $alreadyFine = 0;
        $failed = 0;

        AudioClip::query()
            ->whereNotNull('thumbnail_path')
            ->orderBy('id')
            ->each(function (AudioClip $clip) use ($storage, $ffmpeg, $dryRun, &$resized, &$alreadyFine, &$failed) {
                try {
                    if ($this->resize($clip, $storage, $ffmpeg, $dryRun)) {
                        $resized++;
                    } else {
                        $alreadyFine++;
                    }
                } catch (\Throwable $e) {
                    // One clip whose file has gone missing, or whose bytes
                    // aren't an image any more, shouldn't stop the rest of the
                    // library being brought up to spec. Say which clip it was
                    // and carry on.
                    $failed++;

                    $this->warn("Clip {$clip->id} ({$clip->thumbnail_path}): {$e->getMessage()}");
                }
            });

        $this->info(
            $dryRun
                ? "Would resize $resized, leave $alreadyFine already at the right size, and couldn't read $failed."
                : "Resized $resized, left $alreadyFine already at the right size, failed on $failed."
        );

        return self::SUCCESS;
    }

    /**
     * Re-encode one clip's artwork in place, or report that it didn't need it.
     *
     * Returns whether the image was resized (in a dry run, whether it would
     * have been). Anything that goes wrong is thrown for the caller to count.
     */
    private function resize(AudioClip $clip, Filesystem $storage, Ffmpeg $ffmpeg, bool $dryRun): bool
    {
        $storedPath = $clip->thumbnail_path;

        if ($storedPath === null || ! $storage->exists($storedPath)) {
            throw new \RuntimeException('There is no file at that path on the disk.');
        }

        $originalPath = null;
        $squarePath = null;

        try {
            // ffmpeg reads from the local filesystem, and the disk needn't be
            // local, so the stored bytes come down to a temporary file first —
            // the same shape as the download job.
            $contents = $storage->get($storedPath);

            if ($contents === null) {
                throw new \RuntimeException('The file at that path could not be read back off the disk.');
            }

            $originalPath = sys_get_temp_dir().'/'.Uuid::uuid4()->toString();

            file_put_contents($originalPath, $contents);

            $size = getimagesize($originalPath);

            if ($size === false) {
                throw new \RuntimeException('The stored file is not an image any decoder here recognises.');
            }

            [$width, $height] = $size;

            if ($width === self::SIDE && $height === self::SIDE) {
                return false;
            }

            if ($dryRun) {
                $this->line("Clip {$clip->id} ({$storedPath}): {$width}x{$height} would become ".self::SIDE.'x'.self::SIDE);

                return true;
            }

            $squarePath = $ffmpeg->imageToSquareJpeg($originalPath, self::SIDE);

            $squareHandle = fopen($squarePath, 'r');

            if (! $squareHandle) {
                throw new \RuntimeException("Couldn't open $squarePath as resource");
            }

            try {
                // Back to the path it came from. Nothing about the naming
                // changes, so the feed keeps pointing at the same URL.
                if (! $storage->put($storedPath, $squareHandle)) {
                    throw new \RuntimeException("Couldn't store the resized artwork at $storedPath");
                }
            } finally {
                fclose($squareHandle);
            }

            return true;
        } finally {
            foreach ([$originalPath, $squarePath] as $temporaryPath) {
                if ($temporaryPath !== null && file_exists($temporaryPath)) {
                    unlink($temporaryPath);
                }
            }
        }
    }
}
