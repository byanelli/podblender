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
        } while (AudioClip::query()->where('storage_path', $path)->exists());

        return $path;
    }

    /**
     * Where a clip's artwork goes, given where its audio goes: the same name
     * with a .jpg extension. Deriving it means the two files sit side by side
     * under the same slug, and nothing has to store or look up a second path
     * to find the image for a clip.
     */
    public static function thumbnailFor(string $audioPath): string
    {
        // A clip created before storage paths were slugs is a bare UUID with no
        // extension at all, so only strip one when there's one to strip.
        $extension = pathinfo($audioPath, PATHINFO_EXTENSION);

        return ($extension === '' ? $audioPath : Str::beforeLast($audioPath, '.')).'.jpg';
    }
}
