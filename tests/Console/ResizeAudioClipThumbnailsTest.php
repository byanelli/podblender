<?php

namespace Tests\Console;

use App\Apis\Ffmpeg\Contracts\Client as Ffmpeg;
use App\Models\AudioClip;
use App\Models\AudioSource;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class ResizeAudioClipThumbnailsTest extends TestCase
{
    /**
     * Every temporary file the faked resize saw, input and output alike.
     *
     * @var array<int, string>
     */
    private array $temporaryImages = [];

    /**
     * The sides the faked resize was asked for, one entry per call.
     *
     * @var array<int, int>
     */
    private array $requestedSides = [];

    private Filesystem $storage;

    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is needed to draw the test images.');
        }

        $this->storage = Storage::fake();

        $record = function (string $path) {
            $this->temporaryImages[] = $path;
        };

        $side = function (int $side) {
            $this->requestedSides[] = $side;
        };

        // A resize that really does produce a square image of the size it was
        // asked for, so the command can be checked on what ends up stored
        // rather than on ffmpeg being called.
        $this->app->bind(Ffmpeg::class, fn () => new readonly class($record, $side) implements Ffmpeg
        {
            public function __construct(private \Closure $record, private \Closure $side) {}

            public function imageToSquareJpeg(string $inputPath, int $maxSide = 1400): string
            {
                $outputPath = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.jpg';

                $image = imagecreatetruecolor($maxSide, $maxSide);
                imagejpeg($image, $outputPath);

                ($this->record)($inputPath);
                ($this->record)($outputPath);
                ($this->side)($maxSide);

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

    /**
     * A clip whose artwork is stored as a JPEG of the given size.
     */
    private function clipWithThumbnail(int $width, int $height, string $path = 'a-channel-a-video.jpg'): AudioClip
    {
        $image = imagecreatetruecolor($width, $height);

        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();

        $this->storage->put($path, $jpeg);

        /** @var AudioClip $clip */
        $clip = AudioClip::factory()->create([
            'audio_source_id' => AudioSource::factory()->create()->id,
            'storage_path'    => str_replace('.jpg', '.mp3', $path),
            'thumbnail_path'  => $path,
        ]);

        return $clip;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function storedDimensions(string $path): array
    {
        $size = getimagesizefromstring((string) $this->storage->get($path));

        $this->assertNotFalse($size, "$path is not a readable image.");

        return [$size[0], $size[1]];
    }

    #[Test]
    public function it_resizes_artwork_that_is_under_apples_minimum(): void
    {
        // A 1280x720 YouTube thumbnail squared off by the old code came out at
        // 720x720, which Apple Podcasts is liable to ignore.
        $clip = $this->clipWithThumbnail(720, 720);

        $this->artisan('clips:resize-thumbnails')->assertSuccessful();

        $this->assertSame([1400, 1400], $this->storedDimensions('a-channel-a-video.jpg'));

        // The path doesn't change, so the feed keeps pointing at the same URL.
        $this->assertSame('a-channel-a-video.jpg', $clip->fresh()->thumbnail_path);
        $this->assertSame([1400], $this->requestedSides);
    }

    #[Test]
    public function it_leaves_artwork_that_is_already_the_right_size_alone(): void
    {
        $this->clipWithThumbnail(1400, 1400);

        $before = $this->storage->get('a-channel-a-video.jpg');

        $this->artisan('clips:resize-thumbnails')
            ->expectsOutputToContain('Resized 0, left 1 already at the right size')
            ->assertSuccessful();

        $this->assertSame($before, $this->storage->get('a-channel-a-video.jpg'));
        $this->assertSame([], $this->requestedSides);
    }

    #[Test]
    public function it_is_safe_to_run_twice(): void
    {
        $this->clipWithThumbnail(720, 720);

        $this->artisan('clips:resize-thumbnails')->assertSuccessful();

        $afterFirstRun = $this->storage->get('a-channel-a-video.jpg');

        // The second pass measures 1400x1400 and has nothing to do, so the
        // image isn't put through another lossy encode.
        $this->artisan('clips:resize-thumbnails')
            ->expectsOutputToContain('Resized 0, left 1 already at the right size')
            ->assertSuccessful();

        $this->assertSame($afterFirstRun, $this->storage->get('a-channel-a-video.jpg'));
    }

    #[Test]
    public function it_scales_a_larger_image_down_as_well(): void
    {
        $this->clipWithThumbnail(2000, 2000);

        $this->artisan('clips:resize-thumbnails')->assertSuccessful();

        $this->assertSame([1400, 1400], $this->storedDimensions('a-channel-a-video.jpg'));
    }

    #[Test]
    public function it_skips_clips_without_any_artwork(): void
    {
        AudioClip::factory()->create([
            'audio_source_id' => AudioSource::factory()->create()->id,
            'thumbnail_path'  => null,
        ]);

        $this->artisan('clips:resize-thumbnails')
            ->expectsOutputToContain('Resized 0, left 0 already at the right size, failed on 0.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_carries_on_past_a_clip_whose_file_is_missing(): void
    {
        /** @var AudioClip $missing */
        $missing = AudioClip::factory()->create([
            'audio_source_id' => AudioSource::factory()->create()->id,
            'thumbnail_path'  => 'never-stored.jpg',
        ]);

        $this->clipWithThumbnail(720, 720, 'a-later-clip.jpg');

        // The clip with no file comes first, so a run that stopped at it would
        // leave the one after it unresized.
        $this->assertLessThan(AudioClip::query()->max('id'), $missing->id);

        $this->artisan('clips:resize-thumbnails')
            ->expectsOutputToContain('Resized 1, left 0 already at the right size, failed on 1.')
            ->assertSuccessful();

        $this->assertSame([1400, 1400], $this->storedDimensions('a-later-clip.jpg'));
    }

    #[Test]
    public function it_carries_on_past_a_file_it_cannot_decode(): void
    {
        $this->storage->put('not-really-an-image.jpg', 'this is not a JPEG');

        AudioClip::factory()->create([
            'audio_source_id' => AudioSource::factory()->create()->id,
            'thumbnail_path'  => 'not-really-an-image.jpg',
        ]);

        $this->clipWithThumbnail(720, 720, 'a-later-clip.jpg');

        $this->artisan('clips:resize-thumbnails')
            ->expectsOutputToContain('Resized 1, left 0 already at the right size, failed on 1.')
            ->assertSuccessful();

        // The unreadable file is left exactly as it was rather than replaced
        // with something ffmpeg made of it.
        $this->assertSame('this is not a JPEG', $this->storage->get('not-really-an-image.jpg'));
    }

    #[Test]
    public function it_deletes_the_temporary_files_it_worked_on(): void
    {
        $this->clipWithThumbnail(720, 720);

        $this->artisan('clips:resize-thumbnails')->assertSuccessful();

        $this->assertCount(2, $this->temporaryImages);

        foreach ($this->temporaryImages as $path) {
            $this->assertFileDoesNotExist($path);
        }
    }

    #[Test]
    public function a_dry_run_reports_what_it_would_do_without_writing_anything(): void
    {
        $this->clipWithThumbnail(720, 720);

        $before = $this->storage->get('a-channel-a-video.jpg');

        $this->artisan('clips:resize-thumbnails', ['--dry-run' => true])
            ->expectsOutputToContain('720x720 would become 1400x1400')
            ->expectsOutputToContain('Would resize 1')
            ->assertSuccessful();

        $this->assertSame($before, $this->storage->get('a-channel-a-video.jpg'));
        $this->assertSame([720, 720], $this->storedDimensions('a-channel-a-video.jpg'));
        $this->assertSame([], $this->requestedSides);
    }
}
