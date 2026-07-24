<?php

namespace App\Apis\Tts\Concerns;

/**
 * Splits a long body of text into whole-word segments no longer than a
 * provider's per-request input limit. Every TTS backend caps how much text one
 * synthesis call accepts, so both clients narrate a segment at a time and stitch
 * the resulting audio back together.
 */
trait SegmentsText
{
    /**
     * Yield successive segments of at most $maxLength characters, never
     * splitting a word. A word that on its own exceeds $maxLength is emitted as
     * its own oversized segment rather than dropped.
     *
     * @return \Generator<int, string>
     */
    private function segmentText(string $text, int $maxLength): \Generator
    {
        $currentSegment = '';

        foreach (preg_split('/\s+/', trim($text)) ?: [] as $word) {
            if ($word === '') {
                continue;
            }

            // The +1 accounts for the space that joining this word would add.
            $wouldOverflow = strlen($currentSegment) + strlen($word) + 1 > $maxLength;

            if ($currentSegment !== '' && $wouldOverflow) {
                yield $currentSegment;
                $currentSegment = '';
            }

            $currentSegment .= ($currentSegment === '' ? '' : ' ').$word;
        }

        if ($currentSegment !== '') {
            yield $currentSegment;
        }
    }
}
