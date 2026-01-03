<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | This determines what cross-origin operations may execute in web browsers.
    | We've added `image-proxy` and wildcard `*` to cover all endpoints.
    |
    */

    // ✅ Allow CORS for Laravel API routes and image-proxy endpoint
    'paths' => ['api/*', 'image-proxy', '*'],

    // Allow all HTTP methods
    'allowed_methods' => ['*'],

    // Allow requests from any origin
    // If you want to restrict, replace '*' with ['https://isent.online']
    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    // Allow all headers
    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // If you need to send cookies or authorization headers, set to true
    'supports_credentials' => false,

];

