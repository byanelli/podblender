<?php

use App\Http\Requests\AudioClipUrlRequest;
use App\Http\Requests\CreateCustomFeedRequest;
use App\Http\Requests\CreateSubscriptionRequest;
use App\Http\Responses\MetadataResponse;

return [

    'typescript' => [

        // Where the generated .d.ts file is written.
        'output' => resource_path('js/roma.d.ts'),

        // Request classes to generate interfaces for.
        'requests' => [
            AudioClipUrlRequest::class,
            CreateCustomFeedRequest::class,
            CreateSubscriptionRequest::class,
        ],

        // Response classes to generate interfaces for.
        'responses' => [
            MetadataResponse::class,
        ],

    ],

];
