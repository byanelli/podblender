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
     * job. A release from one of the middlewares below isn't an exception, so it never touches this counter: only a
     * real error thrown out of handle() does. Three of those and we give up, having tried the download three times.
     * See retryUntil() for why the attempt count can't do this job.
     */
    public int $maxExceptions = 3;

    /**
     * The smallest timeout we'll allow, so a short clip never regresses below a
     * comfortably generous budget (the old fixed value).
     */
    private const TIMEOUT_FLOOR_SECONDS = 3600;

    /**
     * Padding on top of the platform's download estimate, covering the parts of
     * the job that aren't the download itself: storing the file, reading its
     * duration, and writing to the database.
     */
    private const BUFFER_SECONDS = 300;

    /**
     * How many attempts to budget time for. A download usually fails
     * transiently and gets retried (see $maxExceptions and backoff()), so the
     * timeout has to fit more than a single attempt's worth of work.
     */
    private const EXPECTED_ATTEMPTS = 3;

    public int $timeout;

    public function __construct(private readonly AudioClip $clip)
    {
        // Scale the timeout with the clip's expected download cost rather than
        // a fixed ceiling: a long article or video legitimately needs more than
        // an hour, and a fixed timeout would doom it. The platform estimates one
        // download conservatively; we add a buffer for the non-download work and
        // multiply by the attempts we expect to spend, floored so short clips
        // keep the old generous budget.
        $this->timeout = $clip->estimated_download_time === null
            ? self::TIMEOUT_FLOOR_SECONDS
            : (int) max(
                self::TIMEOUT_FLOOR_SECONDS,
                ($clip->estimated_download_time + self::BUFFER_SECONDS) * self::EXPECTED_ATTEMPTS,
            );
    }

    /**
     * What YouTube reacts badly to is a burst of downloads, and subscribing to a channel can create a lot of clips at
     * once. Horizon runs several worker processes, so without these two the backlog would go out as fast as the
     * workers could pick it up: concurrently, and from one IP address.
     */
    /**
     * @return array<int, object>
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
     * Laravel ignores the attempt limit ($tries) entirely when this method is present, which is exactly what we want:
     * releases keep the job alive for up to 12 hours, and only genuine exceptions — capped by $maxExceptions — end it.
     */
    public function retryUntil(): CarbonImmutable
    {
        return CarbonImmutable::now()->addHours(12);
    }

    /**
     * Wait between genuine retries. A download usually fails because the platform is rate-limiting or briefly blocking
     * us, and hammering it again immediately is the surest way to make that worse, so back off further each time.
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
            // Load related feeds (we'll need these later to dispatch events).
            $this->clip->load(AudioClip::REL_FEEDS, AudioClip::REL_AUDIO_SOURCE);

            $platform = $platforms->for($this->clip->platform_type);

            // Download the audio from the platform into a temporary file and open the downloaded file.
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

            // Success is a terminal outcome the UI reacts to: the clip has appeared in the feed.
            $this->broadcastFinishedProcessing($events);
        } catch (ContentUnavailableException $e) {
            // The platform told us this content is gone for good. That's terminal too — retrying would only ask again
            // and get the same answer — so mark it and let the UI stop showing it as processing.
            $this->clip->processing_state = ClipProcessingState::Unavailable;
            $this->clip->save();

            $this->broadcastFinishedProcessing($events);
        }
        // Any other exception is left to propagate. It's a transient failure — a rate limit, a proxy timeout — so the
        // clip stays Processing and the job is retried (see $maxExceptions and backoff()). We deliberately don't
        // broadcast here: the UI should keep showing "processing", because that's still true. Only when the retries
        // are exhausted does failed() run, and that's where the clip is marked Failed and the UI told to stop waiting.
        finally {
            // Whether the download succeeded or failed, delete the temporary file. Never delete the clip: a failed
            // download is worth retrying, and throwing the record away would lose the metadata we'd retry against.
            if ($downloadPath !== null && file_exists($downloadPath)) {
                unlink($downloadPath);
            }
        }
    }

    /**
     * Called once the retries are exhausted (or the job is otherwise permanently failed). The clip never made it into
     * the feed, so record that and tell the UI to stop showing it as processing.
     */
    public function failed(?\Throwable $e): void
    {
        $this->clip->processing_state = ClipProcessingState::Failed;
        $this->clip->save();

        $this->broadcastFinishedProcessing(app(Dispatcher::class));
    }

    /**
     * Tell each feed the clip belongs to that it's finished processing, so a page watching that feed can update. Only
     * call this on a terminal outcome — success, permanently unavailable, or permanently failed — never on a transient
     * failure that's about to be retried.
     */
    private function broadcastFinishedProcessing(Dispatcher $events): void
    {
        $this->clip->loadMissing(AudioClip::REL_FEEDS);

        foreach ($this->clip->feeds as $feed) {
            $events->dispatch(new FinishedProcessingClip($feed));
        }
    }
}
