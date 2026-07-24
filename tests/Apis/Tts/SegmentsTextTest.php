<?php

namespace Tests\Apis\Tts;

use App\Apis\Tts\Concerns\SegmentsText;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SegmentsTextTest extends TestCase
{
    private object $segmenter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->segmenter = new class
        {
            use SegmentsText;

            /** @return array<int, string> */
            public function split(string $text, int $maxLength): array
            {
                return iterator_to_array($this->segmentText($text, $maxLength));
            }
        };
    }

    #[Test]
    public function it_keeps_short_text_in_one_segment()
    {
        $this->assertEquals(['hello world'], $this->segmenter->split('hello world', 4096));
    }

    #[Test]
    public function it_splits_on_word_boundaries_and_never_drops_a_word()
    {
        // 10-char words; a 25-char budget fits two words plus the joining space
        // ("aaaaaaaaaa bbbbbbbbbb" = 21), but a third would overflow.
        $words = ['aaaaaaaaaa', 'bbbbbbbbbb', 'cccccccccc', 'dddddddddd'];
        $segments = $this->segmenter->split(implode(' ', $words), 25);

        $this->assertEquals(
            ['aaaaaaaaaa bbbbbbbbbb', 'cccccccccc dddddddddd'],
            $segments
        );

        // The word the old implementation dropped at a boundary survives.
        $this->assertEquals(implode(' ', $words), implode(' ', $segments));
    }

    #[Test]
    public function it_emits_a_word_longer_than_the_limit_as_its_own_segment()
    {
        $long = str_repeat('x', 50);

        $this->assertEquals([$long], $this->segmenter->split($long, 25));
        $this->assertEquals(['hello', $long], $this->segmenter->split('hello '.$long, 25));
    }

    #[Test]
    public function it_ignores_extra_whitespace_and_yields_nothing_for_empty_input()
    {
        $this->assertEquals([], $this->segmenter->split('', 25));
        $this->assertEquals([], $this->segmenter->split("   \n\t  ", 25));
        $this->assertEquals(['a b c'], $this->segmenter->split("  a   b\n c  ", 25));
    }
}
