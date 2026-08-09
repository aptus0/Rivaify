<?php

return [
    'name' => 'Commerce',
    'payments' => [
        'default' => env('PAYMENT_GATEWAY', 'paytr'),
        'storefront_providers' => array_values(array_filter(array_map(
            static fn (string $provider): string => mb_strtolower(trim($provider)),
            explode(',', (string) env('STOREFRONT_PAYMENT_PROVIDERS', 'paytr')),
        ))),
        'allow_manual_storefront' => (bool) env('STOREFRONT_ALLOW_MANUAL_PAYMENT', false),
        'paytr' => [
            'merchant_id' => env('PAYTR_MERCHANT_ID'),
            'merchant_key' => env('PAYTR_MERCHANT_KEY'),
            'merchant_salt' => env('PAYTR_MERCHANT_SALT'),
            'test_mode' => (bool) env('PAYTR_TEST_MODE', true),
            'debug' => (bool) env('PAYTR_DEBUG', false),
            'timeout' => (int) env('PAYTR_TIMEOUT', 30),
            'max_installment' => (int) env('PAYTR_MAX_INSTALLMENT', 0),
            'no_installment' => (bool) env('PAYTR_NO_INSTALLMENT', false),
            'token_url' => env('PAYTR_TOKEN_URL', 'https://www.paytr.com/odeme/api/get-token'),
            'iframe_url' => env('PAYTR_IFRAME_URL', 'https://www.paytr.com/odeme/guvenli'),
            'refund_url' => env('PAYTR_REFUND_URL', 'https://www.paytr.com/odeme/iade'),
        ],
    ],
];
