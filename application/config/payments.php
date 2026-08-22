<?php

return [
    'enabled' => (bool) env('PAYMENTS_ENABLED', false),
    'provider' => env('PAYMENTS_PROVIDER'),
    'dintero' => [
        'environment' => env('DINTERO_ENVIRONMENT', 'test'),
        'test_account_id' => env('DINTERO_TEST_ACCOUNT_ID'),
        'production_account_id' => env('DINTERO_PRODUCTION_ACCOUNT_ID'),
        'client_id' => env('DINTERO_CLIENT_ID'),
        'client_secret' => env('DINTERO_CLIENT_SECRET'),
        'profile_id' => env('DINTERO_PROFILE_ID', 'default'),
        'callback_secret' => env('DINTERO_CALLBACK_SECRET'),
        'require_callback_signature' => (bool) env('DINTERO_REQUIRE_CALLBACK_SIGNATURE', true),
        'api_base_url' => env('DINTERO_API_BASE_URL', 'https://api.dintero.com/v1'),
        'connect_timeout' => (int) env('DINTERO_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('DINTERO_TIMEOUT', 10),
        'vat_percent' => (int) env('DINTERO_VAT_PERCENT', 25),
    ],
];
