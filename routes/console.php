<?php

use App\Apis\Tts\SegmentCache;
use App\Jobs\UpdateAllSubscriptions;
use Illuminate\Support\Facades\Schedule;

// The only place UpdateAllSubscriptions is dispatched. Without it, a subscription is filled once at creation and never
// updated. Two hours keeps feeds current while limiting requests to the platforms.
Schedule::job(new UpdateAllSubscriptions)->everyTwoHours();

// Deletes segments of abandoned narrations. A successful narration deletes its own.
Schedule::call(fn (SegmentCache $cache) => $cache->prune())->hourly()->name('prune-tts-segments');
