<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FRONTEND_URL', 'http://localhost:3000'))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    /*
    | Auth actual: Sanctum Bearer en la API.
    | El front Next usa BFF (cookie httpOnly) y proxy same-origin;
    | el browser no envía cookies cross-origin a Laravel → credentials=false.
    */
    'supports_credentials' => false,

];
