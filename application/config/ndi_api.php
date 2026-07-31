<?php

return [
    // Disabled by default so test suites and unfinished checkout flows cannot call NDI.
    'inquiry_enabled' => (bool) env('NDI_INQUIRY_ENABLED', false),
    // C1 mutates NDI state and always has its own stricter switch.
    'order_enabled' => (bool) env('NDI_ORDER_ENABLED', false),
    'c1_test_mode' => (bool) env('NDI_C1_TEST_MODE', false),
    'fulfilment_read_enabled' => (bool) env('NDI_FULFILMENT_READ_ENABLED', false),
    'endpoint' => env('NDI_API_ENDPOINT', 'https://salesapi.ndias.com/EndPoint'),
    'username' => env('NDI_API_USERNAME'),
    'password' => env('NDI_API_PASSWORD'),
    'party_id' => env('NDI_API_PARTY_ID'),
    'agency_code' => env('NDI_API_AGENCY_CODE'),
    'connect_timeout_seconds' => (int) env('NDI_API_CONNECT_TIMEOUT', 3),
    'timeout_seconds' => (int) env('NDI_API_TIMEOUT', 8),
    'inquiry_ttl_seconds' => (int) env('NDI_INQUIRY_TTL_SECONDS', 300),
];
