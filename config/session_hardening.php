<?php

return [
    'enabled' => (bool) env('SESSION_HOST_AWARE', env('APP_ENV') === 'production'),

    'host_only' => (bool) env('SESSION_HOST_ONLY', true),

    'hosts' => [
        'app.rivaify.com' => [
            'cookie' => env('APP_SESSION_COOKIE', '__Host-rivaify_app_session'),
            'same_site' => env('APP_SESSION_SAME_SITE', 'lax'),
            'lifetime' => (int) env('APP_SESSION_LIFETIME', env('SESSION_LIFETIME', 120)),
        ],

        'admin.rivaify.com' => [
            'cookie' => env('APP_SESSION_COOKIE', '__Host-rivaify_app_session'),
            'same_site' => env('APP_SESSION_SAME_SITE', 'lax'),
            'lifetime' => (int) env('APP_SESSION_LIFETIME', env('SESSION_LIFETIME', 120)),
        ],

        'ins.rivaify.com' => [
            'cookie' => env('INTERNAL_SESSION_COOKIE', '__Host-rivaify_ins_session'),
            'same_site' => env('INTERNAL_SESSION_SAME_SITE', 'strict'),
            'lifetime' => (int) env('INTERNAL_SESSION_LIFETIME', 30),
        ],
    ],
];
