<?php

namespace App\Apis\Tts\Concerns;

/**
 * Splits text into whole-word segments no longer than a provider's
 * per-request input limit. Each client narrates the segments separately and
 * concatenates the audio.
 */
trait SegmentsText
{
    /**
     * Yield successive segments of at most $maxLength characters, never
     * splitting a word. A single word longer than $maxLength is yielded as an
     * oversized segment.
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
