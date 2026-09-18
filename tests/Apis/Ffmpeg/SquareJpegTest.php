<?php

namespace Tests\Apis\Ffmpeg;

use App\Apis\Ffmpeg\Client;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

/**
 * Runs the vendored ffmpeg for real.
 *
 * The rest of the suite fakes it, which proves the client passes the arguments
 * it means to but says nothing about what ffmpeg does with them — and the crop
 * filter is exactly the kind of expression that reads correctly and produces
 * something else. So these few tests hand it real pixels and measure what comes
 * back, and skip themselves where the binary or GD isn't installed.
 */
class SquareJpegTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is needed to draw the test images.');
        }

        if (! file_exists(base_path('vendor/bin/ffmpeg'))) {
            $this->markTestSkipped('The vendored ffmpeg binary is not installed.');
        }

        Process::preventStrayProcesses(false);
    }

    /**
     * Draw a solid PNG of the given size and return where it was written.
     */
    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 120, 200));

        imagepng($image, $path = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.png');

        return $path;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function dimensions(string $path): array
    {
        $size = getimagesize($path);

        $this->assertNotFalse($size, "$path is not an image ffmpeg could read back.");

        return [$size[0], $size[1], $size[2]];
    }

    #[Test]
    public function it_crops_a_widescreen_image_to_a_square_at_the_full_side()
    {
        // 1280x720 is YouTube's best thumbnail, so this is the case that
        // matters most: the centre 720x720 of the frame, enlarged to 1400.
        /** @var Client $client */
        $client = $this->app->make(Client::class);

        [$width, $height, $type] = $this->dimensions($client->imageToSquareJpeg($this->png(1280, 720)));

        $this->assertSame($width, $height);
        $this->assertSame(1400, $width);
        $this->assertSame(IMAGETYPE_JPEG, $type);
    }

    #[Test]
    public function it_scales_a_large_square_down_to_the_given_side()
    {
        /** @var Client $client */
        $client = $this->app->make(Client::class);

        [$width, $height] = $this->dimensions($client->imageToSquareJpeg($this->png(2000, 2000)));

        $this->assertSame(1400, $width);
        $this->assertSame(1400, $height);
    }

    #[Test]
    public function it_enlarges_an_image_smaller_than_the_given_side()
    {
        // Apple Podcasts wants artwork of at least 1400 pixels square and is
        // liable to ignore anything smaller, so even a thumbnail this far under
        // the minimum is stretched rather than stored as it arrived.
        /** @var Client $client */
        $client = $this->app->make(Client::class);

        [$width, $height] = $this->dimensions($client->imageToSquareJpeg($this->png(120, 90)));

        $this->assertSame(1400, $width);
        $this->assertSame(1400, $height);
    }

    #[Test]
    public function it_honours_a_side_other_than_the_default()
    {
        /** @var Client $client */
        $client = $this->app->make(Client::class);

        [$width, $height] = $this->dimensions($client->imageToSquareJpeg($this->png(1280, 720), 2000));

        $this->assertSame(2000, $width);
        $this->assertSame(2000, $height);
    }

    #[Test]
    public function it_handles_an_odd_number_of_pixels()
    {
        // A square with an odd side can't be split into 2x2 chroma blocks, and
        // some encoders refuse it outright. Real thumbnails do come in odd
        // sizes, so make sure this one encodes rather than failing the job.
        /** @var Client $client */
        $client = $this->app->make(Client::class);

        [$width, $height] = $this->dimensions($client->imageToSquareJpeg($this->png(721, 405)));

        $this->assertSame(1400, $width);
        $this->assertSame(1400, $height);
    }

    #[Test]
    public function its_output_is_an_rgb_jpeg_with_no_alpha_channel()
    {
        // Apple's spec is RGB; a JPEG that decodes as CMYK, or a PNG's alpha
        // carried through, is artwork some clients won't render.
        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $path = $client->imageToSquareJpeg($this->png(1280, 720));

        $image = imagecreatefromjpeg($path);

        $this->assertNotFalse($image, "$path could not be read back as a JPEG.");
        $this->assertFalse(imageistruecolor($image) && imagecolortransparent($image) !== -1);

        // JPEG has no alpha, so what matters is that the pixels came back as a
        // recognisable colour rather than as a channel-swapped mess.
        $colour = imagecolorsforindex($image, imagecolorat($image, 700, 700));

        $this->assertEqualsWithDelta(20, $colour['red'], 12);
        $this->assertEqualsWithDelta(120, $colour['green'], 12);
        $this->assertEqualsWithDelta(200, $colour['blue'], 12);
        $this->assertSame(0, $colour['alpha']);
    }
}
