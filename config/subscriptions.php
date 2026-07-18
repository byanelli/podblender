<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backfill Window
    |--------------------------------------------------------------------------
    |
    | How far back, in months, a brand-new subscription reaches when it's first
    | filled in. A subscriber's feed only shows clips published on or after the
    | day they subscribed, so this sets that day to N months ago: the platform's
    | recent back catalogue turns up straight away rather than only clips
    | published from the moment of subscribing onward.
    |
    | Raise it to pull in more history when someone subscribes; lower it to keep
    | a new feed to only its most recent episodes.
    |
    */

    'backfill_months' => (int) env('SUBSCRIPTION_BACKFILL_MONTHS', 1),

];
