<?php

use App\Jobs\UpdateAllSubscriptions;
use Illuminate\Support\Facades\Schedule;

// Keep every subscription's clips flowing in. Nothing else dispatches UpdateAllSubscriptions, so without this line a
// subscription is filled once when it's created and then never updated again. Every two hours is the cadence chosen
// for how fresh a podcast feed needs to be against how gently we have to treat the platforms we download from.
Schedule::job(new UpdateAllSubscriptions)->everyTwoHours();
