<?php

namespace App\Covers;

use App\Covers\Contracts\CoverGenerator;
use GdImage;
use Ramsey\Uuid\Uuid;

/**
 * Draws a feed's show artwork with PHP's GD extension: a blue-green gradient
 * with the feed's name centered on it in white, over a hard offset shadow.
 *
 * Needs only GD and FreeType, which are installed locally and on the server.
 * Drawing a cover takes about 80 milliseconds, so it runs during the request
 * and not on a queue.
 */
final class GdCoverGenerator implements CoverGenerator
{
    /**
     * Apple Podcasts accepts show artwork from 1400 to 3000 pixels square. A
     * gradient with a few large glyphs gains nothing from more pixels, so this
     * is the minimum.
     */
    public const int SIDE = 1400;

    private const int JPEG_QUALITY = 90;

    /**
     * The font is in the repo because the production server has no system
     * fonts. It is a static ExtraBold instance of Baloo 2, the app's display
     * face, because GD can't select a weight from the variable font the browser
     * loads.
     */
    private const string FONT = 'fonts/Baloo2-ExtraBold.ttf';

    /**
     * The five backgrounds, as [start color, end color, angle]. They are sRGB
     * conversions of the .clip-placeholder-1 to -5 gradients in
     * resources/css/app.css, which are in oklch. GD only takes RGB.
     *
     * The angle follows CSS: degrees clockwise from straight up, pointing from
     * the start color towards the end color.
     *
     * The fifth pair differs from .clip-placeholder-5, whose pale mint end
     * gives white text too little contrast. Its light end is darker here and
     * its dark end is green.
     *
     * @var list<array{0: array{int, int, int}, 1: array{int, int, int}, 2: int}>
     */
    private const array GRADIENTS = [
        [[193, 246, 226], [0, 116, 116], 135],  // mint -> deep teal
        [[0, 116, 116], [101, 208, 220], 0],    // deep teal -> sky
        [[166, 237, 240], [0, 106, 62], 200],   // pale blue -> deep green
        [[161, 236, 187], [0, 135, 127], 60],   // spring green -> teal
        [[100, 199, 226], [0, 118, 83], 90],    // sky -> deep green
    ];

    /**
     * The gradient is drawn pixel by pixel on a small canvas and scaled up,
     * which loses nothing for a smooth gradient. At full size it would take two
     * million imagesetpixel() calls. 175 divides 1400 exactly.
     */
    private const int GRADIENT_CANVAS = 175;

    /** How much of the square the title may cover, leaving a margin around it. */
    private const float TEXT_WIDTH = 0.80;

    private const float TEXT_HEIGHT = 0.70;

    /** How far above center the title is drawn, as a fraction of the square. */
    private const float OPTICAL_LIFT = 0.02;

    /**
     * The title starts at the maximum size and shrinks in steps until it fits.
     * The maximum keeps a one-word name from filling the square. Below the
     * minimum, text is illegible when the artwork is shown an inch wide on a
     * phone, so a title that doesn't fit there is truncated.
     */
    private const int MAX_FONT_SIZE = 360;

    private const int MIN_FONT_SIZE = 80;

    private const int FONT_SIZE_STEP = 10;

    /**
     * Characters removed before measuring: pictographs and emoji, their
     * joiners and variation selectors, and the private use area. GD draws one
     * font in one color, so a color emoji renders as an empty box or as
     * nothing, and its width still shifts the rest of the title.
     */
    private const string UNDRAWABLE = '/[\x{1F000}-\x{1FAFF}\x{2190}-\x{2BFF}\x{FE00}-\x{FE0F}'
        .'\x{E000}-\x{F8FF}\x{200D}\x{20E3}\x{3030}\x{303D}]/u';

    private readonly string $font;

    public function __construct()
    {
        $this->font = resource_path(self::FONT);
    }

    public function generate(string $title, int $variant): string
    {
        if (! is_file($this->font)) {
            throw new \RuntimeException("The cover font is missing from {$this->font}");
        }

        // The images are not freed explicitly. Since PHP 8.0 a GdImage is freed
        // with its last reference, and imagedestroy() is deprecated as of 8.5.
        $image = $this->background($variant);

        $this->drawTitle($image, $this->drawable($title));

        $path = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.jpg';

        if (! imagejpeg($image, $path, self::JPEG_QUALITY)) {
            throw new \RuntimeException("Couldn't write a cover to {$path}");
        }

        return $path;
    }

    /**
     * The gradient square, without the title.
     */
    private function background(int $variant): GdImage
    {
        $count = count(self::GRADIENTS);

        // The double modulo keeps a negative $variant in range.
        [$from, $to, $angle] = self::GRADIENTS[(($variant % $count) + $count) % $count];

        $small = imagecreatetruecolor(self::GRADIENT_CANVAS, self::GRADIENT_CANVAS);

        // Project each pixel onto the gradient's direction, then rescale so
        // the result runs from 0 at one corner to 1 at the opposite corner.
        $radians = deg2rad($angle);
        $dx = sin($radians);
        $dy = -cos($radians);
        $half = (abs($dx) + abs($dy)) * self::GRADIENT_CANVAS / 2;
        $center = self::GRADIENT_CANVAS / 2;

        for ($y = 0; $y < self::GRADIENT_CANVAS; $y++) {
            for ($x = 0; $x < self::GRADIENT_CANVAS; $x++) {
                $along = ((($x - $center) * $dx + ($y - $center) * $dy) / $half + 1) / 2;
                $along = max(0.0, min(1.0, $along));

                imagesetpixel($small, $x, $y, $this->color(
                    $small,
                    (int) round($from[0] + ($to[0] - $from[0]) * $along),
                    (int) round($from[1] + ($to[1] - $from[1]) * $along),
                    (int) round($from[2] + ($to[2] - $from[2]) * $along),
                ));
            }
        }

        $image = imagecreatetruecolor(self::SIDE, self::SIDE);

        imagecopyresampled(
            $image, $small,
            0, 0, 0, 0,
            self::SIDE, self::SIDE,
            self::GRADIENT_CANVAS, self::GRADIENT_CANVAS,
        );

        return $image;
    }

    /**
     * Draw the title centered on the square.
     */
    private function drawTitle(GdImage $image, string $title): void
    {
        if ($title === '') {
            // A feed with no drawable name gets the gradient alone, which still
            // meets Apple's artwork requirements.
            return;
        }

        [$lines, $size] = $this->fit($title);

        $ink = $this->color($image, 16, 42, 48);
        $white = $this->color($image, 255, 255, 255);
        $shadow = max(5, (int) round($size * 0.05));
        $lineHeight = $this->lineHeight($size);

        // Center on the glyphs' bounding boxes. Baloo 2's baseline is low in
        // its em box (see the underline stroke in app.css), so centering on em
        // boxes puts the title visibly low.
        $ascent = -$this->boundingBox($size, $lines[0])[5];
        $descent = $this->boundingBox($size, $lines[array_key_last($lines)])[1];
        $blockHeight = $ascent + (count($lines) - 1) * $lineHeight + $descent;

        // Two corrections. The shadow extends below and to the right of each
        // glyph, so the text is shifted half a shadow up and left. Text at
        // the measured center also looks low, so it is raised by OPTICAL_LIFT.
        $lift = $shadow / 2 + self::SIDE * self::OPTICAL_LIFT;
        $baseline = (int) round((self::SIDE - $blockHeight) / 2 + $ascent - $lift);

        foreach ($lines as $line) {
            $box = $this->boundingBox($size, $line);
            $x = (int) round((self::SIDE - ($box[2] - $box[0])) / 2 - $box[0] - $shadow / 2);

            imagettftext($image, $size, 0, $x + $shadow, $baseline + $shadow, $ink, $this->font, $line);
            imagettftext($image, $size, 0, $x, $baseline, $white, $this->font, $line);

            $baseline += $lineHeight;
        }
    }

    /**
     * The largest size the title fits at, and the lines it breaks into there.
     *
     * @return array{0: non-empty-list<string>, 1: int}
     */
    private function fit(string $title): array
    {
        // First try every size without breaking words. A smaller title reads
        // better than a broken word.
        for ($size = self::MAX_FONT_SIZE; $size >= self::MIN_FONT_SIZE; $size -= self::FONT_SIZE_STEP) {
            $lines = $this->wrap($title, $size, breakWords: false);

            if ($lines !== null && $lines !== [] && $this->fitsHeight($lines, $size)) {
                return [$lines, $size];
            }
        }

        // A word is wider than a line even at the minimum size, so words must
        // be broken. Start from the maximum size again.
        for ($size = self::MAX_FONT_SIZE; $size >= self::MIN_FONT_SIZE; $size -= self::FONT_SIZE_STEP) {
            $lines = $this->wrap($title, $size, breakWords: true) ?? [];

            if ($lines !== [] && $this->fitsHeight($lines, $size)) {
                return [$lines, $size];
            }
        }

        // Too tall even at the minimum size. Keep the lines that fit and end
        // them with an ellipsis.
        $size = self::MIN_FONT_SIZE;
        $lines = $this->wrap($title, $size, breakWords: true) ?? [];
        $maxLines = max(1, intdiv($this->textHeight(), $this->lineHeight($size)));

        return [$this->truncate($lines, $maxLines, $size), $size];
    }

    /**
     * @param  list<string>  $lines
     */
    private function fitsHeight(array $lines, int $size): bool
    {
        return count($lines) * $this->lineHeight($size) <= $this->textHeight();
    }

    /**
     * Break the title into lines that fit the text box at this size, measured
     * with the font.
     *
     * A word wider than a line is broken where the line is full, without a
     * hyphen, because a hyphen would read as part of the name.
     *
     * @return list<string>|null Null when a word doesn't fit and cutting it wasn't allowed.
     */
    private function wrap(string $title, int $size, bool $breakWords): ?array
    {
        $lines = [];
        $line = '';
        $width = $this->textWidth();

        foreach (preg_split('/\s+/u', $title, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if ($this->measure($size, $word) > $width) {
                if (! $breakWords) {
                    return null;
                }

                // A broken word starts on a new line. Its last piece stays
                // open so the following words can join it.
                if ($line !== '') {
                    $lines[] = $line;
                }

                $pieces = $this->breakWord($word, $size);
                $line = (string) array_pop($pieces);
                $lines = [...$lines, ...$pieces];

                continue;
            }

            if ($line === '') {
                $line = $word;

                continue;
            }

            if ($this->measure($size, "{$line} {$word}") > $width) {
                $lines[] = $line;
                $line = $word;

                continue;
            }

            $line .= " {$word}";
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Cut a single word into the widest pieces that each fit a line.
     *
     * @return non-empty-list<string>
     */
    private function breakWord(string $word, int $size): array
    {
        $width = $this->textWidth();
        $pieces = [];
        $piece = '';

        foreach (mb_str_split($word) as $character) {
            if ($piece !== '' && $this->measure($size, $piece.$character) > $width) {
                $pieces[] = $piece;
                $piece = '';
            }

            $piece .= $character;
        }

        if ($piece !== '') {
            $pieces[] = $piece;
        }

        return $pieces === [] ? [$word] : $pieces;
    }

    /**
     * Keep the lines that fit and end them with an ellipsis.
     *
     * @param  list<string>  $lines
     * @return non-empty-list<string>
     */
    private function truncate(array $lines, int $maxLines, int $size): array
    {
        if ($lines === []) {
            return [''];
        }

        if (count($lines) <= $maxLines) {
            return $lines;
        }

        $kept = array_slice($lines, 0, $maxLines);
        $last = $kept[$maxLines - 1];

        // Remove characters from the end until the ellipsis fits.
        while ($last !== '' && $this->measure($size, $last.'…') > $this->textWidth()) {
            $last = mb_substr($last, 0, mb_strlen($last) - 1);
        }

        $kept[$maxLines - 1] = rtrim($last).'…';

        return array_values($kept);
    }

    /**
     * The title without invalid bytes, emoji, and the extra whitespace their
     * removal leaves. Scripts Baloo 2 has no glyphs for are kept and render as
     * empty boxes.
     */
    private function drawable(string $title): string
    {
        if (! mb_check_encoding($title, 'UTF-8')) {
            $title = mb_convert_encoding($title, 'UTF-8', 'UTF-8');
        }

        $title = (string) preg_replace(self::UNDRAWABLE, '', $title);

        return trim((string) preg_replace('/\s+/u', ' ', $title));
    }

    /**
     * The width of $text at this size, in pixels.
     */
    private function measure(int $size, string $text): int
    {
        $box = $this->boundingBox($size, $text);

        return $box[2] - $box[0];
    }

    /**
     * The corners of $text as imagettfbbox() returns them, relative to the
     * drawing origin. The upper edge is negative because it is above the
     * baseline.
     *
     * @return array<int, int>
     */
    private function boundingBox(int $size, string $text): array
    {
        $box = imagettfbbox($size, 0, $this->font, $text);

        if ($box === false) {
            throw new \RuntimeException("Couldn't measure \"{$text}\" in {$this->font}");
        }

        return $box;
    }

    /**
     * Allocate a color, clamped to the range GD accepts. GD returns false when
     * allocation fails, and drawing with false gives an arbitrary color, so
     * this throws.
     */
    private function color(GdImage $image, int $red, int $green, int $blue): int
    {
        $color = imagecolorallocate(
            $image,
            max(0, min(255, $red)),
            max(0, min(255, $green)),
            max(0, min(255, $blue)),
        );

        if ($color === false) {
            throw new \RuntimeException("Couldn't allocate the color {$red},{$green},{$blue}");
        }

        return $color;
    }

    /**
     * The distance between baselines. Baloo 2 has tall ascenders and deep
     * descenders, and with a smaller multiplier a "p" on one line touches an
     * "h" on the next.
     */
    private function lineHeight(int $size): int
    {
        return (int) round($size * 1.18);
    }

    private function textWidth(): int
    {
        return (int) (self::SIDE * self::TEXT_WIDTH);
    }

    private function textHeight(): int
    {
        return (int) (self::SIDE * self::TEXT_HEIGHT);
    }
}
