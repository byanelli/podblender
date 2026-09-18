<?php

namespace App\Support;

use App\Models\Feed;
use Illuminate\Support\Str;

/**
 * Builds a storage path for a feed's show artwork, like
 * "covers/long-reads-for-the-commute-3f9k2a.jpg".
 *
 * The random token is not only there to keep names apart. Podcast apps cache
 * show artwork by its URL and are slow to look again, so a feed whose cover is
 * redrawn has to publish it under a name it has never used before — otherwise
 * listeners keep seeing the old picture.
 *
 * Covers sit in their own folder rather than beside the clips, because clip
 * audio and clip artwork are named after the clip's slug and a feed's slug
 * could match one of them.
 */
final class FeedCoverStoragePath
{
    public static function for(string $name): string
    {
        $base = Str::slug($name);
        $base = $base === '' ? 'feed' : Str::limit($base, 100, '');

        do {
            $token = Str::lower(Str::random(6));
            $path = "covers/{$base}-{$token}.jpg";
        } while (Feed::query()->where('cover_path', $path)->exists());

        return $path;
    }
}
