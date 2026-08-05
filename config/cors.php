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

    // This host (api.rivaify.com) serves nothing but API/Fortify auth
    // endpoints — Fortify's own routes (/login, /register, /user/password,
    // ...) live outside /api/*, so scoping this to api/* would silently
    // exclude them rather than the app having a mixed web+api surface.
    'paths' => ['*'],

    'allowed_methods' => ['*'],

    // Wildcard is incompatible with supports_credentials below (browsers
    // reject it) — the merchant/admin SPAs are first-party, known hosts,
    // so list them explicitly rather than opening this to any origin.
    // Full scheme-qualified origins, e.g. "https://app.rivaify.com,https://admin.rivaify.com".
    'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
