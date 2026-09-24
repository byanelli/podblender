<?php

namespace App\Jobs;

use App\Apis\Ffmpeg\Contracts\Client as Ffmpeg;
use App\Enums\ClipProcessingState;
use App\Events\FinishedProcessingClip;
use App\Jobs\Concerns\InjectsFailureDependencies;
use App\Models\AudioClip;
use App\Platforms\Exceptions\ContentUnavailableException;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\Platforms;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class DownloadAndStoreAudioClip implements ShouldQueue
{
    use Dispatchable, InjectsFailureDependencies, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Name of the rate limiter, and the prefix of the WithoutOverlapping lock. The limiter is registered in
     * App\Providers\AppServiceProvider. Both are per platform, since a platform blocks by its own measure.
     */
    public const string THROTTLE = 'audio-clip-downloads';

    /**
     * The job fails after three exceptions thrown from handle(). A release by the middleware below is not an exception
     * and doesn't count. See retryUntil() for why $tries isn't used.
     */
    public int $maxExceptions = 3;

    /**
     * Minimum timeout, so a short clip still gets an hour.
     */
    private const TIMEOUT_FLOOR_SECONDS = 3600;

    /**
     * Added to the platform's download estimate to cover the rest of the job:
     * storing the file, reading its duration, and writing to the database.
     */
    private const BUFFER_SECONDS = 300;

    /**
     * Number of download attempts the timeout allows time for. Failed downloads
     * are retried (see $maxExceptions and backoff()).
     */
    private const EXPECTED_ATTEMPTS = 3;

    public int $timeout;

    public function __construct(private readonly AudioClip $clip)
    {
        // (platform's estimate for one download + buffer) x expected attempts,
        // with a floor. A long article or video can need more than an hour.
        $this->timeout = $clip->estimated_download_time === null
            ? self::TIMEOUT_FLOOR_SECONDS
            : (int) max(
                self::TIMEOUT_FLOOR_SECONDS,
                ($clip->estimated_download_time + self::BUFFER_SECONDS) * self::EXPECTED_ATTEMPTS,
            );
    }

    /**
     * A burst of downloads can get this host blocked by a platform, and subscribing to a channel can create many clips
     * at once. Horizon runs several worker processes, so without these middlewares the backlog would download
     * concurrently from one IP address.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            // One download per platform at a time across all workers.
            (new WithoutOverlapping($this->throttleKey()))->releaseAfter(30)->expireAfter($this->timeout),

            // Space consecutive downloads apart.
            new RateLimited(self::THROTTLE),
        ];
    }

    /**
     * The key that the lock and the rate limiter count downloads under.
     */
    public function throttleKey(): string
    {
        return self::THROTTLE.':'.$this->clip->platform_type->name;
    }

    /**
     * Both middlewares release the job back onto the queue, and with a large backlog a job may be released many times
     * before it runs, so the attempt count doesn't indicate failure. When this method is present Laravel ignores
     * $tries: releases keep the job alive for up to 12 hours, and only $maxExceptions exceptions fail it.
     */
    public function retryUntil(): CarbonImmutable
    {
        return CarbonImmutable::now()->addHours(12);
    }

    /**
     * Delays between retries after an exception. A download usually fails because the platform is rate-limiting or
     * briefly blocking this host, so each retry waits longer.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /**
     * @throws PlatformException
     */
    public function handle(
        Platforms $platforms,
        Filesystem $storage,
        Ffmpeg $ffmpeg,
        Dispatcher $events,
    ): void {
        $downloadPath = null;

        try {
            // The feeds are needed later to dispatch events.
            $this->clip->load('feeds', 'audioSource');

            $platform = $platforms->for($this->clip->platform_type);

            $download = $platform->downloadAudio($this->clip->platform_url);
            $downloadPath = $download->path;
            $downloadHandle = fopen($downloadPath, 'r');

            $duration = $ffmpeg->getDuration($downloadPath);

            if (! $downloadHandle) {
                throw new \Exception("Couldn't open $downloadPath as resource");
            }

            $storageResult = $storage->put($this->clip->storage_path, $downloadHandle);

            if (! $storageResult) {
                throw new \Exception("Couldn't store audio from $downloadPath");
            }

            // The RSS feed needs the file size and duration.
            $this->clip->processing_state = ClipProcessingState::Processed;
            $this->clip->duration = $duration;
            $this->clip->size = $storage->size($this->clip->storage_path);

            if ($download->ttsUsage !== null) {
                $this->clip->tts_model = $download->ttsUsage->model;
                $this->clip->tts_input_tokens = $download->ttsUsage->inputTokens;
                $this->clip->tts_output_tokens = $download->ttsUsage->outputTokens;
                $this->clip->tts_cost = $download->ttsUsage->cost;
            }

            $this->clip->save();

            $this->broadcastFinishedProcessing($events);
        } catch (ContentUnavailableException $e) {
            // The content is permanently unavailable, so don't retry. The broadcast stops the UI showing the clip as
            // processing.
            $this->clip->processing_state = ClipProcessingState::Unavailable;
            $this->clip->save();

            $this->broadcastFinishedProcessing($events);
        }
        // Any other exception propagates so the job is retried (see $maxExceptions and backoff()). The clip stays
        // Processing and nothing is broadcast until failed() runs.
        finally {
            // Delete the temporary file on success or failure. The clip record stays, since a retry needs its metadata.
            if ($downloadPath !== null && file_exists($downloadPath)) {
                unlink($downloadPath);
            }
        }
    }

    /**
     * Called once the retries are exhausted or the job otherwise fails permanently. Marks the clip Failed and tells the
     * UI to stop showing it as processing.
     */
    public function handleFailure(?\Throwable $e, Dispatcher $events): void
    {
        $this->clip->processing_state = ClipProcessingState::Failed;
        $this->clip->save();

        $this->broadcastFinishedProcessing($events);
    }

    /**
     * Tell each of the clip's feeds that it has finished processing, so a page watching that feed can update. Call
     * this only on a final outcome (processed, unavailable, or failed), never on a failure that will be retried.
     */
    private function broadcastFinishedProcessing(Dispatcher $events): void
    {
        $this->clip->loadMissing('feeds');

        foreach ($this->clip->feeds as $feed) {
            $events->dispatch(new FinishedProcessingClip($feed));
        }
    }
}
