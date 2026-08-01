<?php

namespace App\Support;

use App\Models\AudioClip;
use Illuminate\Support\Str;

/**
 * Builds a human-readable storage path for an audio clip, like
 * "the-daily-ai-notes-3f9k2a.mp3", so downloads and RSS/enclosure URLs are
 * recognisable rather than an opaque UUID. A short random token keeps names
 * unique in the unlikely event two clips share an author and title.
 */
final class AudioClipStoragePath
{
    public static function for(string $author, string $title): string
    {
        $base = Str::slug("{$author} {$title}");
        $base = $base === '' ? 'clip' : Str::limit($base, 100, '');

        do {
            $token = Str::lower(Str::random(6));
            $path = "{$base}-{$token}.mp3";
        } while (AudioClip::query()->where(AudioClip::COL_STORAGE_PATH, $path)->exists());

        return $path;
    }
}
