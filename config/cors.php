<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'],

    'allowed_origins' => [env('APP_FE_URL', ''), 'http://127.0.0.1:5173', 'http://localhost:5173', 'https://a1-integ-fe-385139891106.asia-southeast1.run.app/', 'https://rci-activeonerx-750380878643.asia-southeast1.run.app/'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Accept', 'Authorization', 'X-Requested-With', 'Access-Control-Allow-Origin', 'Access-Control-Allow-Credentials', 'Access-Control-Allow-Headers', 'Access-Control-Allow-Methods', 'Access-Control-Expose-Headers', 'Access-Control-Max-Age', 'Access-Control-Request-Headers', 'Access-Control-Request-Method'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
