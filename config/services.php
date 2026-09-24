<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark'          => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses'               => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend'            => [
        // Receiving email needs a full-access key. A sending-only key can't read received messages.
        'key'            => env('RESEND_KEY'),

        // The signing secret of the Resend webhook for received email.
        'webhook_secret' => env('RESEND_WEBHOOK_SECRET'),

        // Feeds' inbound addresses are at this domain. Blank turns the feature off.
        'inbound_domain' => env('RESEND_INBOUND_DOMAIN'),
    ],

    'slack'             => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'youtube_data_api'  => [
        'key' => env('YOUTUBE_DATA_API_KEY'),
    ],

    'gemini'            => [
        // Generative Language API key (aistudio.google.com/apikey). Used for
        // text-to-speech via App\Apis\Tts\GeminiClient.
        'api_key' => env('GEMINI_API_KEY'),
        'tts'     => [
            'model'  => env('GEMINI_TTS_MODEL', 'gemini-3.8-flash-lite-tts'),
            'voice'  => env('GEMINI_TTS_VOICE', 'Aoede'),

            // USD per million tokens, from ai.google.dev/gemini-api/docs/pricing.
            // Each price applies from its date until the next one.
            'prices' => [
                'gemini-3.8-flash-lite-tts'    => [
                    '2026-01-01' => ['input' => 0.50, 'output' => 6.00],
                    '2027-01-01' => ['input' => 1.00, 'output' => 12.00],
                ],
                'gemini-3.8-flash-tts'         => [
                    '2026-01-01' => ['input' => 0.50, 'output' => 9.00],
                    '2027-01-01' => ['input' => 1.00, 'output' => 18.00],
                ],
                'gemini-3.1-flash-tts-preview' => [
                    '2026-01-01' => ['input' => 1.00, 'output' => 20.00],
                ],
            ],
        ],
    ],

    // Which scraping service fetches pages a plain HTTP client can't, such as archive.is behind Cloudflare.
    'scraper'           => [
        'provider' => env('SCRAPER_PROVIDER', 'scrapfly'),
    ],

    'scrapfly'          => [
        // Anti-Scraping-Protection scrape API, used to clear archive.is's
        // Cloudflare CAPTCHA for gated articles. Every scrape spends credits.
        'key' => env('SCRAPFLY_API_KEY'),
    ],

    'zyte'              => [
        // Zyte API, the other scraping service. Pay as you go, per successful response.
        'key' => env('ZYTE_API_KEY'),
    ],

    'ytdlp'             => [
        // How long to remember that YouTube has refused this host's address, and so skip straight to the residential
        // proxy. The refusal lasts hours, and the only cost of guessing short is one wasted download attempt.
        'direct_block_minutes' => env('YTDLP_DIRECT_BLOCK_MINUTES', 60),
    ],

    // Which residential proxy provider the app uses, one of 'oxylabs' or 'dataimpulse'. Only one is in play at a
    // time, and only the chosen one's credentials are read. The default keeps installs that predate DataImpulse
    // working exactly as they did.
    'residential_proxy' => [
        'provider' => env('RESIDENTIAL_PROXY_PROVIDER', 'oxylabs'),
    ],

    'oxylabs'           => [
        'residential' => [
            'user'     => env('OXYLABS_USERNAME'),
            'password' => env('OXYLABS_PASSWORD'),

            // The country to take an exit address in. YouTube serves some countries poorly or not at all, and an
            // address near the content is faster.
            'country'  => env('OXYLABS_COUNTRY', 'US'),
        ],
    ],

    'dataimpulse'       => [
        'residential' => [
            'user'     => env('DATAIMPULSE_USERNAME'),
            'password' => env('DATAIMPULSE_PASSWORD'),

            // The country to take an exit address in. DataImpulse wants a lowercase two-letter code; the proxy class
            // lowercases whatever is written here.
            'country'  => env('DATAIMPULSE_COUNTRY', 'US'),
        ],
    ],
];
