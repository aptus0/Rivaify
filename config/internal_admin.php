<?php

return [
    'host' => env('INTERNAL_ADMIN_HOST', 'ins.rivaify.com'),

    'enforce_private_network' => (bool) env('INTERNAL_ADMIN_ENFORCE_PRIVATE_NETWORK', false),

    'require_two_factor' => (bool) env('INTERNAL_ADMIN_REQUIRE_2FA', false),

    'allowed_cidrs' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('INTERNAL_ADMIN_ALLOWED_CIDRS', '10.8.0.0/24,127.0.0.1/32,::1/128')),
    ))),
];
