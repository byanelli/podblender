<?php

namespace App\Jobs;

use App\Apis\Ffmpeg\Contracts\Client as Ffmpeg;
use App\Enums\ClipProcessingState;
use App\Events\FinishedProcessingClip;
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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Names both the lock that keeps downloads from overlapping and the rate limiter that spaces them out. The limiter
     * itself is registered in App\Providers\AppServiceProvider.
     */
    public const string THROTTLE = 'audio-clip-downloads';

    /**
     * Downloading is the one thing here that can get us blocked, so only a genuine failure should count against the
     * job. See retryUntil() for why attempts aren't a useful bound.
     */
    public int $maxExceptions = 1;

    public int $timeout;

    public function __construct(private readonly AudioClip $clip)
    {
        // Allow this job to run for up to an hour, because the download may involve exponential backoffs and a
        // failover to a residential proxy. The timeout also includes time spent within this job storing the file and
        // updating the database.
        $this->timeout = 3600;
    }

    /**
     * What YouTube reacts badly to is a burst of downloads, and subscribing to a channel can create a lot of clips at
     * once. Horizon runs several worker processes, so without these two the backlog would go out as fast as the
     * workers could pick it up: concurrently, and from one IP address.
     */
    public function middleware(): array
    {
        return [
            // Never download two clips at the same time, no matter how many workers are free.
            (new WithoutOverlapping(self::THROTTLE))->releaseAfter(30)->expireAfter($this->timeout),

            // Having serialized them, leave a gap between one download and the next.
            new RateLimited(self::THROTTLE),
        ];
    }

    /**
     * Both middlewares above release the job back onto the queue rather than failing it, and a large backlog means a
     * job may be released many times before its turn comes around. That makes the number of attempts a meaningless
     * measure of whether this job is failing, so bound it by wall-clock time and let $maxExceptions bound the errors.
     * Laravel ignores the attempt limit entirely when this method is present.
     */
    public function retryUntil(): CarbonImmutable
    {
        return CarbonImmutable::now()->addHours(12);
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
        try {
            // Load related feeds (we'll need these later to dispatch events).
            $this->clip->load(AudioClip::REL_FEEDS, AudioClip::REL_AUDIO_SOURCE);

            $platform = $platforms->for($this->clip->platform_type);

            // Download the audio from the platform into a temporary file and open the downloaded file.
            // todo: handle ContentUnavailable exception, mark content permanently unavailable
            $downloadPath = $platform->downloadAudio($this->clip->platform_url);
            $downloadHandle = fopen($downloadPath, 'r');

            // Use ffmpeg to get the duration.
            $duration = $ffmpeg->getDuration($downloadPath);

            if (! $downloadHandle) {
                throw new \Exception("Couldn't open $downloadPath as resource");
            }

            // Store the file.
            $storageResult = $storage->put($this->clip->storage_path, $downloadHandle);

            if (! $storageResult) {
                throw new \Exception("Couldn't store audio from $downloadPath");
            }

            // Mark the clip as no longer processing and save the file size and duration in the database (for use in the
            // RSS feed).
            $this->clip->processing_state = ClipProcessingState::Processed;
            $this->clip->duration = $duration;
            $this->clip->size = $storage->size($this->clip->storage_path);
            $this->clip->save();
        } catch (ContentUnavailableException $e) {
            // If the platform reported that we didn't have permission to access this clip, mark it as such in the
            // database.
            $this->clip->processing_state = ClipProcessingState::Unavailable;
            $this->clip->save();
        } catch (\Exception $e) {
            // If there was an error, delete the clip so we don't leave it around in an intermediate state.
            // Todo: provide an option to retry a failed download?
            $this->clip->delete();

            throw $e;
        } finally {
            // Whether the operation succeeded or failed, delete the temporary file.
            if (isset($downloadPath) && file_exists($downloadPath)) {
                unlink($downloadPath);
            }

            // Dispatch an event to each feed indicating the clip is finished processing.
            foreach ($this->clip->feeds as $feed) {
                $events->dispatch(new FinishedProcessingClip($feed));
            }
        }
    }
}
