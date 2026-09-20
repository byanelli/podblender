<?php

namespace Tests\Covers;

use App\Covers\GdCoverGenerator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GdCoverGeneratorTest extends TestCase
{
    private GdCoverGenerator $generator;

    /** @var list<string> */
    private array $paths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new GdCoverGenerator;
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    #[Test]
    public function it_draws_a_square_rgb_jpeg_for_a_short_title()
    {
        $this->assertIsAppleSizedJpeg($this->generate('Lectures', 1));
    }

    #[Test]
    public function it_draws_a_square_rgb_jpeg_for_a_long_multi_word_title()
    {
        $this->assertIsAppleSizedJpeg($this->generate(
            'Paris School of Economics: Public Lectures and Seminars 2026', 2
        ));
    }

    #[Test]
    public function it_draws_a_square_rgb_jpeg_for_a_single_very_long_word()
    {
        // With no space to wrap at, the word is broken across lines.
        $this->assertIsAppleSizedJpeg($this->generate(
            'Donaudampfschifffahrtsgesellschaftskapitän', 3
        ));
    }

    #[Test]
    public function it_draws_a_square_rgb_jpeg_for_an_empty_title()
    {
        $this->assertIsAppleSizedJpeg($this->generate('   ', 4));
    }

    #[Test]
    public function it_draws_a_square_rgb_jpeg_for_a_title_with_emoji()
    {
        // GD can't draw colour emoji, so they're dropped.
        $this->assertIsAppleSizedJpeg($this->generate('🎧 Deep Work 🚀', 0));
    }

    #[Test]
    public function it_draws_a_square_rgb_jpeg_for_a_title_the_font_has_no_glyphs_for()
    {
        $this->assertIsAppleSizedJpeg($this->generate('日本語のポッドキャスト', 1));
    }

    #[Test]
    public function an_empty_title_leaves_the_background_alone()
    {
        // The gradients never reach white, so the only white on a cover is its
        // title.
        $this->assertSame(0, $this->whitePixels($this->generate('   ', 0)));
        $this->assertGreaterThan(0, $this->whitePixels($this->generate('Lectures', 0)));
    }

    #[Test]
    public function a_title_made_only_of_emoji_is_treated_as_an_empty_one()
    {
        $this->assertSame(0, $this->whitePixels($this->generate('🎧🚀', 0)));
    }

    #[Test]
    public function the_same_variant_always_gives_the_same_gradient()
    {
        // The corner is background for any title, so its colour identifies the
        // gradient.
        $this->assertSame(
            $this->corner($this->generate('Lectures', 7)),
            $this->corner($this->generate('Adam Tooze', 7)),
        );
    }

    #[Test]
    public function different_variants_give_different_gradients()
    {
        $corners = [];

        foreach (range(0, 4) as $variant) {
            $corners[] = $this->corner($this->generate('Lectures', $variant));
        }

        $this->assertCount(5, array_unique($corners));
    }

    #[Test]
    public function the_variant_wraps_round_so_any_feed_id_picks_a_gradient()
    {
        // Feed ids exceed the number of gradients, and a caller may pass a
        // negative number.
        $this->assertSame(
            $this->corner($this->generate('Lectures', 2)),
            $this->corner($this->generate('Lectures', 7)),
        );

        $this->assertSame(
            $this->corner($this->generate('Lectures', 3)),
            $this->corner($this->generate('Lectures', -2)),
        );
    }

    private function generate(string $title, int $variant): string
    {
        return $this->paths[] = $this->generator->generate($title, $variant);
    }

    /**
     * Apple Podcasts requires square RGB artwork of at least 1400 pixels, as a
     * JPEG or a PNG. getimagesize() reports three channels for an RGB JPEG and
     * four for a CMYK one.
     */
    private function assertIsAppleSizedJpeg(string $path): void
    {
        $size = getimagesize($path);

        $this->assertNotFalse($size);
        $this->assertSame(GdCoverGenerator::SIDE, $size[0]);
        $this->assertSame(GdCoverGenerator::SIDE, $size[1]);
        $this->assertSame(IMAGETYPE_JPEG, $size[2]);
        $this->assertSame(3, $size['channels']);
        $this->assertSame(8, $size['bits']);
    }

    /**
     * The number of sampled pixels that are white. On a cover, a white pixel is
     * part of a letter.
     */
    private function whitePixels(string $path): int
    {
        $image = imagecreatefromjpeg($path);

        $this->assertNotFalse($image);

        $white = 0;

        for ($y = 0; $y < GdCoverGenerator::SIDE; $y += 8) {
            for ($x = 0; $x < GdCoverGenerator::SIDE; $x += 8) {
                $colour = imagecolorat($image, $x, $y);

                if (min(($colour >> 16) & 255, ($colour >> 8) & 255, $colour & 255) > 238) {
                    $white++;
                }
            }
        }

        return $white;
    }

    private function corner(string $path): int
    {
        $image = imagecreatefromjpeg($path);

        $this->assertNotFalse($image);

        return imagecolorat($image, 4, 4);
    }
}
