<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Joins a clip to a feed it appears in.
 *
 * published_at is the date this clip is presented as published *in this feed*, which is what the RSS feed reports as
 * its pubDate and what podcast players order episodes by. It isn't a property of the clip: a lecture published two
 * years ago is two years old in a subscription to the channel, but brand new in a feed someone just added it to by
 * hand.
 *
 * @property ?CarbonImmutable $published_at
 */
class AudioClipFeed extends Pivot
{
    protected $casts = [
        'published_at' => 'datetime',
    ];
}
