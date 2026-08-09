<?php

return [
    'name' => 'Ecosystem',

    /*
     * Per-connector platform credentials (the Rivaify-side OAuth app, not
     * any merchant's own secrets — those live encrypted in
     * store_integrations.credentials). A connector is only marked
     * "available" in the registry once its required keys here are set.
     */
    'connectors' => [
        'meta' => [
            'app_id' => env('META_APP_ID'),
            'app_secret' => env('META_APP_SECRET'),
            'api_version' => env('META_GRAPH_API_VERSION', 'v21.0'),
            'redirect_uri' => env('META_OAUTH_REDIRECT_URI', env('APP_URL').'/integrations/meta/callback'),
        ],
    ],

    'oauth_state_ttl_minutes' => 10,
];
