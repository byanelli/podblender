<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Minutes Between Downloads
    |--------------------------------------------------------------------------
    |
    | How long to leave between one audio clip download and the next. Downloads
    | that arrive in a burst are what gets an IP address blocked by YouTube, and
    | a podcast feed is read minutes or hours after it's published, so there's
    | little to gain from downloading a backlog quickly and a lot to lose.
    |
    | Raise this if downloads start failing; lower it if a new subscription
    | takes too long to fill in.
    |
    */

    'minutes_between_downloads' => (int) env('MINUTES_BETWEEN_DOWNLOADS', 2),

];
