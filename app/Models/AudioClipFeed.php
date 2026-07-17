<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Joins a clip to a feed it appears in.
 *
 * @property ?CarbonImmutable $published_at
 */
class AudioClipFeed extends Pivot
{
    /**
     * The date this clip is presented as published *in this feed*, which is what the RSS feed reports as its pubDate
     * and what podcast players order episodes by. It isn't a property of the clip: a lecture published two years ago
     * is two years old in a subscription to the channel, but brand new in a feed someone just added it to by hand.
     */
    const string COL_PUBLISHED_AT = 'published_at';

    protected $casts = [
        self::COL_PUBLISHED_AT => 'datetime',
    ];
}
