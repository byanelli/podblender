<?php

namespace App\Support;

use App\Models\Feed;
use Illuminate\Support\Str;

/**
 * Builds a storage path for a feed's show artwork, like
 * "covers/long-reads-for-the-commute-3f9k2a.jpg".
 *
 * Podcast apps cache show artwork by URL and rarely refetch it, so a
 * regenerated cover needs a new path or listeners keep seeing the old image.
 * The random token provides that, as well as keeping paths unique.
 *
 * Covers go in their own folder because a feed's slug could match a clip's,
 * and clip audio and artwork are stored under the clip's slug.
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
