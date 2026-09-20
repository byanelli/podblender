<?php

namespace App\Support;

use App\Models\AudioClip;
use Illuminate\Support\Str;

/**
 * Builds a readable storage path for an audio clip, like
 * "the-daily-ai-notes-3f9k2a.mp3", so downloads and RSS enclosure URLs are
 * recognizable. The random token keeps paths unique when two clips share an
 * author and title.
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
        } while (AudioClip::query()->where('storage_path', $path)->exists());

        return $path;
    }

    /**
     * The storage path for a clip's artwork: its audio path with a .jpg
     * extension.
     */
    public static function thumbnailFor(string $audioPath): string
    {
        // Some older clips have a bare UUID as their path, with no extension.
        $extension = pathinfo($audioPath, PATHINFO_EXTENSION);

        return ($extension === '' ? $audioPath : Str::beforeLast($audioPath, '.')).'.jpg';
    }
}
