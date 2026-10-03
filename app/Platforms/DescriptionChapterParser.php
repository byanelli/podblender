<?php

namespace App\Platforms;

use App\Platforms\Contracts\Chapter;

/**
 * Reads chapters from a list of timestamped lines in a description, such as "02:55 Speedrunning the stages of grief".
 *
 * Follows YouTube's rules for chapters in a video description: the first timestamp is 0:00, there are at least three,
 * and they increase. A description that breaks a rule has no chapters, as on YouTube.
 */
final class DescriptionChapterParser
{
    private const int MINIMUM_CHAPTERS = 3;

    // An optional bullet or bracket, a timestamp (M:SS, MM:SS or H:MM:SS), an optional separator, then the title.
    private const string LINE = '/^[\s•*\-–—]*[(\[]?(?:(\d{1,2}):)?(\d{1,2}):(\d{2})[)\]]?\s*(?:[-–—:|]\s*)?(\S.*)$/u';

    /**
     * @return list<Chapter>
     */
    public static function parse(string $description): array
    {
        $chapters = [];

        foreach (preg_split('/\R/u', $description) ?: [] as $line) {
            if (! preg_match(self::LINE, trim($line), $match)) {
                continue;
            }

            [, $hours, $minutes, $seconds, $title] = $match;

            if ((int) $seconds >= 60 || ($hours !== '' && (int) $minutes >= 60)) {
                continue;
            }

            $chapters[] = new Chapter(
                startSeconds: (int) $hours * 3600 + (int) $minutes * 60 + (int) $seconds,
                title: trim($title),
            );
        }

        return self::isValid($chapters) ? $chapters : [];
    }

    /**
     * @param  list<Chapter>  $chapters
     */
    private static function isValid(array $chapters): bool
    {
        if (count($chapters) < self::MINIMUM_CHAPTERS || $chapters[0]->startSeconds !== 0) {
            return false;
        }

        for ($i = 1; $i < count($chapters); $i++) {
            if ($chapters[$i]->startSeconds <= $chapters[$i - 1]->startSeconds) {
                return false;
            }
        }

        return true;
    }
}
