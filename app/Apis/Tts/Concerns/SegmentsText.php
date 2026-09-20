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
     * Yield successive segments of at most $maxLength bytes, never splitting a
     * word. A word longer than $maxLength is truncated to fit.
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

            // A word this long isn't prose (e.g. a URL or encoded data).
            // mb_strcut cuts by bytes without splitting a character.
            if (strlen($word) > $maxLength) {
                $word = mb_strcut($word, 0, $maxLength, 'UTF-8');
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
