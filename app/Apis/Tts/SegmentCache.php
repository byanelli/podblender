<?php

namespace App\Apis\Tts;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Ramsey\Uuid\Uuid;

/**
 * Narrated segments, kept until the narration they belong to succeeds, so a retry pays only for the segments that
 * failed. Each entry is an MP3 and a JSON file with the tokens it was billed for.
 *
 * Failures here are reported and otherwise ignored: without the cache a narration costs more but still works.
 */
readonly class SegmentCache
{
    public const string DISK = 'tts-segments';

    // Longer than DownloadAndStoreAudioClip::retryUntil() (12 hours), so every retry of a narration finds its segments.
    public const int TTL_SECONDS = 24 * 60 * 60;

    private Filesystem $disk;

    public function __construct(FilesystemFactory $filesystems)
    {
        $this->disk = $filesystems->disk(self::DISK);
    }

    /**
     * A local copy of the segment's MP3, which the caller deletes, and its usage as [inputTokens, outputTokens]. Null
     * on a miss.
     *
     * @return array{0: string, 1: array{0: int, 1: int}|null}|null
     */
    public function get(string $id): ?array
    {
        $key = $this->key($id);

        try {
            // The JSON is written first, so an MP3 without one is being written or pruned.
            if (! $this->disk->exists("$key.json") || ! $this->disk->exists("$key.mp3")) {
                return null;
            }

            $entry = json_decode((string) $this->disk->get("$key.json"), true);

            if (! is_array($entry) || ! array_key_exists('usage', $entry)) {
                return null;
            }

            $usage = is_array($entry['usage'])
                ? [(int) $entry['usage'][0], (int) $entry['usage'][1]]
                : null;

            $source = $this->disk->readStream("$key.mp3");
            is_resource($source) || throw new \RuntimeException("Couldn't read cached segment $key.mp3");

            $path = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.mp3';

            try {
                $target = fopen($path, 'w');
                is_resource($target) || throw new \RuntimeException("Couldn't open $path");
                stream_copy_to_stream($source, $target);
                fclose($target);
            } finally {
                fclose($source);
            }

            return [$path, $usage];
        } catch (\Throwable $e) {
            report($e);

            if (isset($path)) {
                @unlink($path);
            }

            return null;
        }
    }

    /**
     * @param  array{0: int, 1: int}|null  $usage
     */
    public function put(string $id, string $mp3Path, ?array $usage): void
    {
        $key = $this->key($id);
        $partial = "$key.mp3.".Uuid::uuid4()->toString().'.partial';

        try {
            $this->disk->put("$key.json", (string) json_encode(['usage' => $usage]));

            $source = fopen($mp3Path, 'r');
            is_resource($source) || throw new \RuntimeException("Couldn't open $mp3Path");

            try {
                $this->disk->writeStream($partial, $source);
            } finally {
                fclose($source);
            }

            // A local move is a rename, so a reader never sees a partly written MP3.
            $this->disk->move($partial, "$key.mp3");
        } catch (\Throwable $e) {
            report($e);

            $this->deleteQuietly([$partial]);
        }
    }

    public function forget(string $id): void
    {
        $key = $this->key($id);

        $this->deleteQuietly(["$key.mp3", "$key.json"]);
    }

    /**
     * Delete entries, and partial writes, older than TTL_SECONDS. These belong to narrations that were abandoned.
     */
    public function prune(): void
    {
        $cutoff = time() - self::TTL_SECONDS;

        foreach ($this->disk->files() as $file) {
            try {
                if ($this->disk->lastModified($file) < $cutoff) {
                    $this->disk->delete($file);
                }
            } catch (\Throwable $e) {
                // Another process may have deleted it since the listing.
                report($e);
            }
        }
    }

    private function key(string $id): string
    {
        return hash('sha256', $id);
    }

    /**
     * @param  array<int, string>  $files
     */
    private function deleteQuietly(array $files): void
    {
        try {
            $this->disk->delete($files);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
