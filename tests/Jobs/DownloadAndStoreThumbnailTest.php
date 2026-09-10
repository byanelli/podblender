<?php

namespace Tests\Jobs;

use App\Apis\Ffmpeg\Contracts\Client as Ffmpeg;
use App\Enums\ClipProcessingState;
use App\Jobs\DownloadAndStoreThumbnail;
use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Platforms\Contracts\RemoteImageThumbnail;
use App\Platforms\Contracts\ThumbnailSource;
use App\Support\AudioClipStoragePath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class DownloadAndStoreThumbnailTest extends TestCase
{
    /**
     * Every temporary file the faked crop saw, input and output alike.
     *
     * @var array<int, string>
     */
    private array $temporaryImages = [];

    protected function setUp(): void
    {
        parent::setUp();

        $record = function (string $path) {
            $this->temporaryImages[] = $path;
        };

        // A crop that copies its input, so what gets stored is what was
        // downloaded, and that notes both paths so a test can check they were
        // tidied away afterwards.
        $this->app->bind(Ffmpeg::class, fn () => new readonly class($record) implements Ffmpeg
        {
            public function __construct(private \Closure $record) {}

            public function imageToSquareJpeg(string $inputPath, int $maxSide = 1400): string
            {
                $outputPath = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.jpg';

                copy($inputPath, $outputPath);

                ($this->record)($inputPath);
                ($this->record)($outputPath);

                return $outputPath;
            }

            public function combineMp3s(array $mp3s): string
            {
                return collect($mp3s)->firstOrFail();
            }

            public function pcmToMp3(string $pcm, int $sampleRate): string
            {
                return $pcm;
            }

            public function getDuration(string $path): int
            {
                return 1;
            }
        });
    }

    private function clip(): AudioClip
    {
        /** @var AudioClip $clip */
        $clip = AudioClip::factory()->create([
            'audio_source_id'  => AudioSource::factory()->create()->id,
            'storage_path'     => 'some-channel-some-video-abc123.mp3',
            'processing_state' => ClipProcessingState::Processing,
        ]);

        return $clip;
    }

    private function source(string $url = 'https://i.ytimg.com/vi/abc123/maxresdefault.jpg'): RemoteImageThumbnail
    {
        return new RemoteImageThumbnail($url);
    }

    private function runJob(AudioClip $clip, ?ThumbnailSource $source = null): void
    {
        $this->app->call([new DownloadAndStoreThumbnail($clip, $source ?? $this->source()), 'handle']);
    }

    #[Test]
    public function it_downloads_squares_off_and_stores_a_thumbnail(): void
    {
        Http::fake(['*' => Http::response('jpeg bytes', headers: ['Content-Type' => 'image/jpeg'])]);

        $storage = Storage::fake();

        $clip = $this->clip();

        $this->runJob($clip);

        $clip = $clip->fresh();

        // The image lands beside the audio, under the same name.
        $this->assertEquals('some-channel-some-video-abc123.jpg', $clip->thumbnail_path);
        $this->assertEquals(
            AudioClipStoragePath::thumbnailFor($clip->storage_path),
            $clip->thumbnail_path
        );
        $storage->assertExists($clip->thumbnail_path);
        $this->assertEquals('jpeg bytes', $storage->get($clip->thumbnail_path));
    }

    #[Test]
    public function it_deletes_the_files_it_worked_on(): void
    {
        Http::fake(['*' => Http::response('jpeg bytes', headers: ['Content-Type' => 'image/jpeg'])]);

        Storage::fake();

        $this->runJob($this->clip());

        $this->assertCount(2, $this->temporaryImages);

        foreach ($this->temporaryImages as $path) {
            $this->assertFileDoesNotExist($path);
        }
    }

    #[Test]
    public function it_does_not_download_a_second_thumbnail_for_a_clip_that_has_one(): void
    {
        Storage::fake();

        $clip = $this->clip();
        $clip->thumbnail_path = 'already-there.jpg';
        $clip->save();

        // Re-dispatching is a normal thing to do — a backfill crossing a
        // subscription update — and the image we already have is the image
        // we'd fetch, so nothing should go out at all.
        $this->runJob($clip);

        Http::assertNothingSent();
        $this->assertEquals('already-there.jpg', $clip->fresh()->thumbnail_path);
    }

    #[Test]
    public function it_refuses_a_response_that_is_not_an_image(): void
    {
        // A platform that has lost an image tends to answer with a page saying
        // so rather than with an error.
        Http::fake(['*' => Http::response('<html>not found</html>', headers: ['Content-Type' => 'text/html'])]);

        Storage::fake();

        $clip = $this->clip();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected an image');

        try {
            $this->runJob($clip);
        } finally {
            $this->assertNull($clip->fresh()->thumbnail_path);
        }
    }

    #[Test]
    public function it_leaves_the_clips_audio_alone_when_the_download_fails(): void
    {
        Http::fake(['*' => Http::response('gone', status: 404)]);

        $storage = Storage::fake();

        $clip = $this->clip();

        try {
            $this->runJob($clip);
            $this->fail('Expected the failed download to propagate so the job is retried.');
        } catch (\Throwable) {
            // expected
        }

        // Artwork is a nicety. A clip whose thumbnail won't download still has
        // its audio, its place in the feed, and its own processing state.
        $clip = $clip->fresh();

        $this->assertNull($clip->thumbnail_path);
        $this->assertEquals(ClipProcessingState::Processing, $clip->processing_state);
        $storage->assertMissing(AudioClipStoragePath::thumbnailFor($clip->storage_path));
    }

    #[Test]
    public function it_rejects_a_thumbnail_source_it_does_not_know_how_to_fetch(): void
    {
        Storage::fake();

        $this->expectException(\InvalidArgumentException::class);

        // Later passes add sources that aren't a URL to fetch. Until one is
        // handled here, saying so is better than storing nothing quietly.
        $this->runJob($this->clip(), new readonly class extends ThumbnailSource {});
    }

    #[Test]
    public function it_leaves_the_thumbnail_unset_once_the_retries_are_exhausted(): void
    {
        $clip = $this->clip();

        (new DownloadAndStoreThumbnail($clip, $this->source()))
            ->failed(new \RuntimeException('the image never arrived'));

        $this->assertNull($clip->fresh()->thumbnail_path);
        $this->assertEquals(ClipProcessingState::Processing, $clip->fresh()->processing_state);
    }
}
