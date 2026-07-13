<?php

return [

    'typescript' => [

        // Where the generated .d.ts file is written.
        'output' => resource_path('js/roma.d.ts'),

        // Directories scanned to auto-detect request and response classes.
        // Requests are classes marked with a class-level #[Request]; responses
        // are classes extending Response (or using IsResponsable).
        'discover' => [
            app_path(),
        ],

        // Additional classes to include beyond what discovery finds.
        'requests' => [],
        'responses' => [],

    ],

];
