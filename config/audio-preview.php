<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Audio Preview
    |--------------------------------------------------------------------------
    |
    | Enable in-browser preview of stored MP3s on the feed page. Preview works
    | by serving the file straight to the browser, which only "just works" for
    | disks whose files are already publicly reachable (the local "public"
    | disk). Cloud disks (S3) need signed URLs, which add expiry/renewal
    | complexity that isn't worth it here, so preview stays gated to local
    | disks. This affects the feed page's play button only: RSS enclosure URLs
    | are always emitted, whatever the disk.
    |
    | Set AUDIO_PREVIEW_ENABLED=false to turn it off entirely.
    |
    */

    'enabled'       => (bool) env('AUDIO_PREVIEW_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Local Disk Drivers
    |--------------------------------------------------------------------------
    |
    | Filesystem drivers whose files are served to the browser directly,
    | without any signing. AudioClip::$preview_url is only populated when the
    | default disk (the one AudioClip uses) uses one of these drivers.
    |
    */

    'local_drivers' => ['local'],
];
