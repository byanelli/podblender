<?php

namespace Tests\Jobs;

use App\Enums\ClipProcessingState;
use App\Events\FinishedProcessingClip;
use App\Jobs\DownloadAndStoreAudioClip;
use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Platforms\Exceptions\ContentUnavailableException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Tests\Concerns\FakesFfmpeg;
use Tests\Concerns\FakesPlatform;
use Tests\Concerns\FakesStorage;
use Tests\TestCase;

class DownloadAndStoreAudioClipTest extends TestCase
{
    use FakesFfmpeg, FakesPlatform, FakesStorage;

    private function clipAttachedToFeed(): AudioClip
    {
        /** @var AudioClip $clip */
        $clip = AudioClip::factory()->create([
            'audio_source_id' => AudioSource::factory()->create()->id,
        ]);

        Feed::factory()->create()->audioClips()->attach($clip, [
            'published_at' => CarbonImmutable::now(),
        ]);

        return $clip;
    }

    #[Test]
    public function it_floors_the_timeout_at_the_legacy_fixed_value_without_an_estimate(): void
    {
        $clip = AudioClip::factory()->create([
            'audio_source_id' => AudioSource::factory()->create()->id,
            'estimated_download_time' => null,
        ]);

        $this->assertEquals(3600, (new DownloadAndStoreAudioClip($clip))->timeout);
    }

    #[Test]
    public function it_scales_the_timeout_with_the_estimate_buffer_and_expected_attempts(): void
    {
        $clip = AudioClip::factory()->create([
            'audio_source_id' => AudioSource::factory()->create()->id,
            'estimated_download_time' => 2400, // e.g. a long article
        ]);

        // (2400 estimate + 300 buffer) * 3 attempts
        $this->assertEquals(8100, (new DownloadAndStoreAudioClip($clip))->timeout);
    }

    #[Test]
    public function it_keeps_the_floor_when_the_scaled_timeout_would_be_smaller(): void
    {
        $clip = AudioClip::factory()->create([
            'audio_source_id' => AudioSource::factory()->create()->id,
            'estimated_download_time' => 100, // short clip: (100+300)*3 = 1200
        ]);

        $this->assertEquals(3600, (new DownloadAndStoreAudioClip($clip))->timeout);
    }

    #[Test]
    public function it_downloads_stores_and_marks_a_clip_processed_and_broadcasts(): void
    {
        Event::fake(FinishedProcessingClip::class);

        $this->fakePlatform(
            audioPath: $downloadPath = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.mp3',
            audioContent: $downloadContents = 'foo',
        );

        $this->fakeFfmpeg($duration = 100);

        $clip = $this->clipAttachedToFeed();

        $storage = Storage::fake();

        $this->assertEquals(ClipProcessingState::Processing, $clip->processing_state);
        $storage->assertMissing($clip->storage_path);

        dispatch(new DownloadAndStoreAudioClip($clip));

        $clip = $clip->fresh();

        $this->assertEquals(ClipProcessingState::Processed, $clip->processing_state);
        $storage->assertExists($clip->storage_path);
        $this->assertEquals($duration, $clip->duration);
        $this->assertEquals($downloadContents, $storage->get($clip->storage_path));
        $this->assertFileDoesNotExist($downloadPath);

        // Success is terminal: the feed should hear about it so the UI stops showing the clip as processing.
        Event::assertDispatched(FinishedProcessingClip::class);
    }

    #[Test]
    public function it_marks_a_clip_unavailable_and_broadcasts_when_the_platform_says_the_content_is_gone(): void
    {
        Event::fake(FinishedProcessingClip::class);

        $this->fakePlatform(downloadError: new ContentUnavailableException);

        $this->fakeFfmpeg();

        $clip = $this->clipAttachedToFeed();

        dispatch(new DownloadAndStoreAudioClip($clip));

        // Unavailable is terminal — retrying would only ask again and get the same answer — so the state sticks and
        // the feed is told, but the clip is never deleted.
        $this->assertModelExists($clip);
        $this->assertEquals(ClipProcessingState::Unavailable, $clip->fresh()->processing_state);
        Event::assertDispatched(FinishedProcessingClip::class);
    }

    #[Test]
    public function it_leaves_the_clip_processing_and_stays_silent_on_a_transient_error(): void
    {
        Event::fake(FinishedProcessingClip::class);

        $this->fakePlatform(
            audioPath: $downloadPath = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.mp3',
        );

        $this->fakeFfmpeg();

        // A generic storage failure stands in for any transient error (a rate limit, a proxy timeout).
        $this->fakeStorageThatThrowsExceptionOnPut();

        $clip = $this->clipAttachedToFeed();

        // Run handle() directly: the point of this path is that it throws and is *retried*, which the sync queue can't
        // model (it would call failed() immediately). The exception propagating is what tells the queue to retry.
        try {
            $this->app->call([new DownloadAndStoreAudioClip($clip), 'handle']);
            $this->fail('Expected the transient error to propagate so the job is retried.');
        } catch (\Throwable $e) {
            // expected
        }

        // The clip survives, stays Processing (the UI should keep waiting), and nothing is broadcast: this isn't a
        // terminal outcome. The temp file is still cleaned up.
        $this->assertModelExists($clip);
        $this->assertEquals(ClipProcessingState::Processing, $clip->fresh()->processing_state);
        $this->assertFileDoesNotExist($downloadPath);
        Event::assertNotDispatched(FinishedProcessingClip::class);
    }

    #[Test]
    public function it_marks_the_clip_failed_and_broadcasts_once_retries_are_exhausted(): void
    {
        Event::fake(FinishedProcessingClip::class);

        $clip = $this->clipAttachedToFeed();

        // failed() is what the queue calls once the retries are used up. That's the terminal failure: record it and
        // tell the feed so the UI stops showing the clip as processing.
        (new DownloadAndStoreAudioClip($clip))->failed(new RuntimeException('download failed for good'));

        $this->assertModelExists($clip);
        $this->assertEquals(ClipProcessingState::Failed, $clip->fresh()->processing_state);
        Event::assertDispatched(FinishedProcessingClip::class);
    }
}
