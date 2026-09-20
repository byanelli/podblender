<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Joins a clip to a feed it appears in.
 *
 * published_at is the date the clip is published in this feed. The RSS reports it as the pubDate, and podcast players
 * order episodes by it. It can differ from the clip's publication date: a two-year-old lecture added to a feed by hand
 * today is published in that feed today.
 *
 * @property ?CarbonImmutable $published_at
 */
class AudioClipFeed extends Pivot
{
    protected $casts = [
        'published_at' => 'datetime',
    ];
}
