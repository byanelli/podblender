<?php

namespace App\Jobs;

use App\Apis\Ffmpeg\Contracts\Client as Ffmpeg;
use App\Models\AudioClip;
use App\Platforms\Contracts\RemoteImageThumbnail;
use App\Platforms\Contracts\ThumbnailSource;
use App\Support\AudioClipStoragePath;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * Fetches a clip's artwork, squares it off, and stores it beside the clip's
 * audio.
 *
 * Artwork is a nicety: an episode without it plays exactly as well as one with
 * it. So nothing here touches processing_state, and a failure — a 404, a
 * platform serving HTML where it promised a JPEG — leaves the clip's audio and
 * its place in the feed alone.
 */
class DownloadAndStoreThumbnail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * A queued job's payload is PHP-serialised, so $source has to be made of
     * plain values. {@see ThumbnailSource}
     */
    public function __construct(
        public readonly AudioClip $clip,
        public readonly ThumbnailSource $source,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(Http $http, Ffmpeg $ffmpeg, Filesystem $storage, LoggerInterface $logger): void
    {
        // The same thumbnail may be queued twice — a backfill crossing a
        // subscription update, say. Whatever we'd fetch the second time is the
        // image we already have, so stop rather than re-fetch and re-store it.
        if ($this->clip->thumbnail_path !== null) {
            $logger->info("Clip {$this->clip->id} already has a thumbnail; not downloading another");

            return;
        }

        $downloadPath = null;
        $squarePath = null;

        try {
            $downloadPath = match (true) {
                $this->source instanceof RemoteImageThumbnail => $this->downloadRemoteImage($http, $this->source),

                // Later passes add sources that aren't a URL to fetch — a cover
                // drawn for a narrated article, for one — and each gets its own
                // arm here.
                default                                       => throw new \InvalidArgumentException(
                    'Unsupported thumbnail source: '.$this->source::class
                ),
            };

            $squarePath = $ffmpeg->imageToSquareJpeg($downloadPath);

            $squareHandle = fopen($squarePath, 'r');

            if (! $squareHandle) {
                throw new \RuntimeException("Couldn't open $squarePath as resource");
            }

            $thumbnailPath = AudioClipStoragePath::thumbnailFor($this->clip->storage_path);

            if (! $storage->put($thumbnailPath, $squareHandle)) {
                throw new \RuntimeException("Couldn't store thumbnail from $squarePath");
            }

            $this->clip->thumbnail_path = $thumbnailPath;
            $this->clip->save();
        } catch (\Throwable $e) {
            // Say which clip and why on every attempt: by the time failed()
            // runs, the interesting part — which of the three attempts failed
            // differently — is gone.
            $logger->warning(
                "Couldn't download a thumbnail for clip {$this->clip->id}: {$e->getMessage()}"
            );

            throw $e;
        } finally {
            foreach ([$downloadPath, $squarePath] as $temporaryPath) {
                if ($temporaryPath !== null && file_exists($temporaryPath)) {
                    unlink($temporaryPath);
                }
            }
        }
    }

    /**
     * Fetch artwork the platform hosts into a temporary file.
     */
    private function downloadRemoteImage(Http $http, RemoteImageThumbnail $source): string
    {
        $response = $http->timeout(30)->get($source->url)->throw();

        // A platform that has lost an image tends to answer with a page saying
        // so rather than an error, and handing that to ffmpeg gets a confusing
        // decode failure instead of a clear one.
        $contentType = (string) $response->header('Content-Type');

        if (! Str::startsWith($contentType, 'image/')) {
            throw new \RuntimeException(
                "Expected an image at {$source->url} but the response was \"{$contentType}\""
            );
        }

        $path = sys_get_temp_dir().'/'.Uuid::uuid4()->toString();

        file_put_contents($path, $response->body());

        return $path;
    }

    /**
     * Called once the retries are exhausted. thumbnail_path stays null, which
     * is exactly what it means: this clip has no artwork, and the episode is
     * served without any.
     */
    public function failed(?\Throwable $e): void
    {
        app(LoggerInterface::class)->error(
            "Gave up downloading a thumbnail for clip {$this->clip->id}: ".($e?->getMessage() ?? 'no reason given')
        );
    }
}
