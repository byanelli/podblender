<?php

namespace App\Covers;

use App\Covers\Contracts\CoverGenerator;
use GdImage;
use Ramsey\Uuid\Uuid;

/**
 * Draws a feed's show artwork with PHP's GD extension: a blue-green gradient
 * with the feed's name centred on it in white, over a hard offset shadow.
 *
 * GD and FreeType are all this needs, and both are already installed here and
 * on the server, so no image library and no headless browser had to be added
 * for it. Drawing one cover costs somewhere around 80 milliseconds, which is
 * why the app can do it while a request is waiting rather than on a queue.
 */
final class GdCoverGenerator implements CoverGenerator
{
    /**
     * Apple Podcasts accepts show artwork from 1400 to 3000 pixels square and
     * rejects anything smaller. Nothing here gets sharper above 1400 — the
     * background is a gradient and the text is a handful of huge glyphs — so
     * the smallest accepted size is also the best one.
     */
    public const int SIDE = 1400;

    private const int JPEG_QUALITY = 90;

    /**
     * The font ships in the repo because the production server has no system
     * fonts at all. It's a static ExtraBold cut of Baloo 2, the app's display
     * face: GD can't pick a weight out of the variable font the browser loads.
     */
    private const string FONT = 'fonts/Baloo2-ExtraBold.ttf';

    /**
     * The five backgrounds, as [start colour, end colour, angle]. They're the
     * sRGB counterparts of the .clip-placeholder-1 to -5 gradients in
     * resources/css/app.css, which stand in for clips that have no thumbnail —
     * a cover and a placeholder for the same app should look related. The
     * values are written out because the CSS states them in oklch, which GD
     * has never heard of.
     *
     * The angle is read the way CSS reads it: degrees clockwise from straight
     * up, pointing from the start colour towards the end colour.
     *
     * The fifth pair is deliberately not a copy of .clip-placeholder-5. That
     * one runs from a medium blue to a pale mint, and white text on the pale
     * end is barely there. A 48-pixel square in a list can get away with it; a
     * 1400-pixel cover carrying nothing but the show's name cannot. So its
     * light end has been pulled down and its dark end deepened into green,
     * which keeps it inside the same palette and distinct from the other four.
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
     * The gradient is drawn one pixel at a time on a small canvas and then
     * scaled up. A smooth gradient loses nothing by it, and two million
     * imagesetpixel() calls would cost more than everything else here put
     * together. 175 divides 1400 exactly.
     */
    private const int GRADIENT_CANVAS = 175;

    /** How much of the square the title may cover, leaving a margin around it. */
    private const float TEXT_WIDTH = 0.80;

    private const float TEXT_HEIGHT = 0.70;

    /** How far above the middle the title sits, as a fraction of the square. */
    private const float OPTICAL_LIFT = 0.02;

    /**
     * The title is set as large as it will go and shrunk in steps until it
     * fits. The ceiling stops a one-word name from filling the square edge to
     * edge. The floor is the point below which a cover stops doing its job:
     * artwork is often seen an inch wide on a phone, and text smaller than
     * this is a grey smudge there. A title that still doesn't fit is cut
     * short instead of shrunk further.
     */
    private const int MAX_FONT_SIZE = 360;

    private const int MIN_FONT_SIZE = 80;

    private const int FONT_SIZE_STEP = 10;

    /**
     * Characters GD can't usefully draw, dropped before anything is measured:
     * pictographs and emoji, the joiners and variation selectors that bind
     * them together, and the private use area. GD draws one font in one
     * colour, so a colour emoji comes out as an empty box or as nothing at
     * all, and its width still pushes the rest of the title around.
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

        // Nothing frees the images: since PHP 8.0 a GdImage is an object that
        // goes when the last reference to it does, and imagedestroy() has been
        // deprecated since 8.5 for saying so.
        $image = $this->background($variant);

        $this->drawTitle($image, $this->drawable($title));

        $path = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.jpg';

        if (! imagejpeg($image, $path, self::JPEG_QUALITY)) {
            throw new \RuntimeException("Couldn't write a cover to {$path}");
        }

        return $path;
    }

    /**
     * The gradient square, before any text goes on it.
     */
    private function background(int $variant): GdImage
    {
        $count = count(self::GRADIENTS);

        // The caller passes a feed id, but nothing stops it passing anything
        // else, and a negative number would index off the front of the table.
        [$from, $to, $angle] = self::GRADIENTS[(($variant % $count) + $count) % $count];

        $small = imagecreatetruecolor(self::GRADIENT_CANVAS, self::GRADIENT_CANVAS);

        // Where each pixel sits along the gradient is how far it has travelled
        // in the gradient's direction, so project it onto that direction and
        // rescale the answer to run from 0 at one corner to 1 at the other.
        $radians = deg2rad($angle);
        $dx = sin($radians);
        $dy = -cos($radians);
        $half = (abs($dx) + abs($dy)) * self::GRADIENT_CANVAS / 2;
        $centre = self::GRADIENT_CANVAS / 2;

        for ($y = 0; $y < self::GRADIENT_CANVAS; $y++) {
            for ($x = 0; $x < self::GRADIENT_CANVAS; $x++) {
                $along = ((($x - $centre) * $dx + ($y - $centre) * $dy) / $half + 1) / 2;
                $along = max(0.0, min(1.0, $along));

                imagesetpixel($small, $x, $y, $this->colour(
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
     * Set the title across the middle of the square.
     */
    private function drawTitle(GdImage $image, string $title): void
    {
        if ($title === '') {
            // A feed with no usable name gets the gradient on its own. That
            // still meets the specification and still looks like this app,
            // which is better than a square with a question mark on it.
            return;
        }

        [$lines, $size] = $this->fit($title);

        $ink = $this->colour($image, 16, 42, 48);
        $white = $this->colour($image, 255, 255, 255);
        $shadow = max(5, (int) round($size * 0.05));
        $lineHeight = $this->lineHeight($size);

        // Centre the ink the glyphs actually cover, not the em boxes they sit
        // in. Baloo 2's baseline is unusually low in its em box (app.css says
        // the same thing about the underline stroke), so measuring the boxes
        // leaves the title looking as though it has slid down the square.
        $ascent = -$this->boundingBox($size, $lines[0])[5];
        $descent = $this->boundingBox($size, $lines[array_key_last($lines)])[1];
        $blockHeight = $ascent + (count($lines) - 1) * $lineHeight + $descent;

        // Two corrections on top of that. The shadow is ink below and to the
        // right of every glyph, so the pair reads as sitting half a shadow
        // further that way than the letters alone do. And text measured dead
        // centre looks low, because the weight of a line sits below its
        // ascenders — so lift it by the small amount that makes it look right.
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
        // Sizes at which every word still fits a line of its own. Setting the
        // whole title smaller always reads better than cutting a word in half,
        // so a break is only worth considering once no size is left that
        // avoids one.
        for ($size = self::MAX_FONT_SIZE; $size >= self::MIN_FONT_SIZE; $size -= self::FONT_SIZE_STEP) {
            $lines = $this->wrap($title, $size, breakWords: false);

            if ($lines !== null && $lines !== [] && $this->fitsHeight($lines, $size)) {
                return [$lines, $size];
            }
        }

        // Some word is wider than a whole line even at the smallest readable
        // size, so it has to be cut. Start large again: a cut word set large is
        // easier to read than a whole one set too small to see.
        for ($size = self::MAX_FONT_SIZE; $size >= self::MIN_FONT_SIZE; $size -= self::FONT_SIZE_STEP) {
            $lines = $this->wrap($title, $size, breakWords: true) ?? [];

            if ($lines !== [] && $this->fitsHeight($lines, $size)) {
                return [$lines, $size];
            }
        }

        // Too tall even at the floor. Keep the lines that fit and mark the cut
        // with an ellipsis: a name nobody can read is no more use than a name
        // that stops early and says so.
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
     * Break the title into lines that fit the text box at this size, measuring
     * with the real font rather than counting characters.
     *
     * A word too wide for a line of its own is either refused — which tells the
     * caller to try a smaller size — or cut where it runs out of room. There's
     * no hyphen on a cut: the break isn't a real one, and a hyphen in the
     * middle of a name reads as part of the name.
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

                // An over-long word gets lines of its own, so the break never
                // shows up as a space in the middle of it. Whatever is left
                // over stays open for the words that follow.
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

        // Take characters off the end until the ellipsis has room beside what
        // is left of the line.
        while ($last !== '' && $this->measure($size, $last.'…') > $this->textWidth()) {
            $last = mb_substr($last, 0, mb_strlen($last) - 1);
        }

        $kept[$maxLines - 1] = rtrim($last).'…';

        return array_values($kept);
    }

    /**
     * What's left of a title once the parts GD can't draw are gone: invalid
     * bytes, emoji, and the runs of whitespace that dropping them leaves
     * behind. Everything else is handed to FreeType as it is, including scripts
     * Baloo 2 has no glyphs for — those come out as empty boxes, which is a
     * poor cover but not a failure, and the alternative is refusing to draw a
     * name its owner can read perfectly well.
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
     * How wide the glyphs of $text run at this size, in pixels.
     */
    private function measure(int $size, string $text): int
    {
        $box = $this->boundingBox($size, $text);

        return $box[2] - $box[0];
    }

    /**
     * Where FreeType would put the corners of $text, relative to the point the
     * text is drawn from. The upper edge comes back negative, because it is
     * above the baseline.
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
     * A colour to draw with, clamped to the range GD accepts. GD hands back
     * false when it can't allocate one, and passing that on as a colour draws
     * something arbitrary instead of failing.
     */
    private function colour(GdImage $image, int $red, int $green, int $blue): int
    {
        $colour = imagecolorallocate(
            $image,
            max(0, min(255, $red)),
            max(0, min(255, $green)),
            max(0, min(255, $blue)),
        );

        if ($colour === false) {
            throw new \RuntimeException("Couldn't allocate the colour {$red},{$green},{$blue}");
        }

        return $colour;
    }

    /**
     * The step from one baseline to the next. Baloo 2 has tall ascenders and
     * deep descenders, so a tighter step than this lets a "p" on one line
     * collide with an "h" on the next — which is easiest to see on a long word
     * broken across several lines.
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
