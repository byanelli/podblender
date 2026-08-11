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

    'postmark'         => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses'              => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend'           => [
        'key' => env('RESEND_KEY'),
    ],

    'slack'            => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'youtube_data_api' => [
        'key' => env('YOUTUBE_DATA_API_KEY'),
    ],

    'gemini'           => [
        // Generative Language API key (aistudio.google.com/apikey). Used for
        // text-to-speech via App\Apis\Tts\GeminiClient.
        'api_key' => env('GEMINI_API_KEY'),
        'tts'     => [
            'model' => env('GEMINI_TTS_MODEL', 'gemini-3.1-flash-tts-preview'),
            'voice' => env('GEMINI_TTS_VOICE', 'Aoede'),
        ],
    ],

    'scrapfly'         => [
        // Anti-Scraping-Protection scrape API, used to clear archive.is's
        // Cloudflare CAPTCHA for gated articles. Every scrape spends credits.
        'key' => env('SCRAPFLY_API_KEY'),
    ],

    'oxylabs'          => [
        'residential' => [
            'user'     => env('OXYLABS_USERNAME'),
            'password' => env('OXYLABS_PASSWORD'),

            // The country to take an exit address in. YouTube serves some countries poorly or not at all, and an
            // address near the content is faster.
            'country'  => env('OXYLABS_COUNTRY', 'US'),
        ],
    ],
];
