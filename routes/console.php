<?php

use App\Jobs\UpdateAllSubscriptions;
use Illuminate\Support\Facades\Schedule;

// The only place UpdateAllSubscriptions is dispatched. Without it, a subscription is filled once at creation and never
// updated. Two hours keeps feeds current while limiting requests to the platforms.
Schedule::job(new UpdateAllSubscriptions)->everyTwoHours();
