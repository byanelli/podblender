<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hard Paywall Domains
    |--------------------------------------------------------------------------
    |
    | Hosts that meter or block every article for a logged-out reader, so a
    | direct fetch is doomed and we go straight to archive.is for them. The
    | seed list is drawn from the Bypass-Paywalls-Clean reference's set of
    | always-metered publishers.
    |
    | This list is owner-editable and expected to self-grow: when a site turns
    | out to be reliably gated, add its bare host here so we stop wasting a
    | direct fetch on it. Match is on the host with any leading "www." removed.
    |
    */

    'hard_paywall_domains' => [
        'nytimes.com',
        'economist.com',
        'wsj.com',
        'washingtonpost.com',
        'ft.com',
        'bloomberg.com',
        'newyorker.com',
        'theatlantic.com',
        'wired.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Paywall Markers
    |--------------------------------------------------------------------------
    |
    | Case-insensitive substrings whose presence in a directly-fetched page is
    | a fuzzy signal that the body is gated. This is the weakest paywall signal
    | and the last one consulted; add strings here as new paywall walls surface.
    |
    */

    'paywall_markers' => [
        'Subscribe to continue',
        'Already a subscriber',
        'Subscribe to read',
        'to continue reading',
        'Create a free account to read',
        'This content is for subscribers only',
    ],

    /*
    |--------------------------------------------------------------------------
    | Paywall Selectors
    |--------------------------------------------------------------------------
    |
    | Known paywall CSS id/class fragments. Presence of any of these strings in
    | the page markup is treated the same as a paywall marker. Kept separate so
    | the two lists can be tuned independently.
    |
    */

    'paywall_selectors' => [
        'paywall',
        'piano-inline',
        'gateway-content',
        'article-gate',
        'subscription-required',
    ],

    /*
    |--------------------------------------------------------------------------
    | Minimum Body Length
    |--------------------------------------------------------------------------
    |
    | An extracted body shorter than this many characters is treated as a
    | hollow/truncated page and a signal to retry through archive.is.
    |
    */

    'min_body_length' => (int) env('ARTICLES_MIN_BODY_LENGTH', 500),

    /*
    |--------------------------------------------------------------------------
    | Archive Base URL
    |--------------------------------------------------------------------------
    |
    | Base of the archive.is mirror used to retrieve the newest snapshot of a
    | gated URL. archive.ph, archive.is, and archive.today are interchangeable
    | front doors to the same service; env-overridable in case one is blocked.
    |
    */

    'archive_base_url' => env('ARTICLES_ARCHIVE_BASE_URL', 'https://archive.ph'),

    /*
    |--------------------------------------------------------------------------
    | User Agent
    |--------------------------------------------------------------------------
    |
    | A realistic browser User-Agent sent on direct fetches. Some publishers
    | serve a stub to anything that looks like a bot, so we pretend to be a
    | current desktop Chrome.
    |
    */

    'user_agent' => env(
        'ARTICLES_USER_AGENT',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
    ),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL (hours)
    |--------------------------------------------------------------------------
    |
    | How long a successfully extracted Article is cached under its normalized
    | URL. Web reads each clip twice (metadata, then download), so the cache
    | collapses that into one fetch. A TTL rather than forever means extractor
    | improvements aren't hidden behind a stale cache.
    |
    */

    'cache_ttl_hours' => (int) env('ARTICLES_CACHE_TTL_HOURS', 168),

];
