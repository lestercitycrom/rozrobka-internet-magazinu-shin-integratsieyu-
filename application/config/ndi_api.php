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
    'sandbox' => [
        // A public, but unmistakably labelled, integration fixture for staging.
        // It uses the normal catalog/cart/order tables and a separately guarded NDI profile.
        'enabled' => (bool) env('NDI_SANDBOX_CATALOG_ENABLED', false),
        'inquiry_enabled' => (bool) env('NDI_SANDBOX_INQUIRY_ENABLED', false),
        'order_enabled' => (bool) env('NDI_SANDBOX_ORDER_ENABLED', false),
        'supplier_code' => 'ndi-sandbox',
        'catalog_sku' => 'NDI-TEST-4765205551649102',
        'party_id' => env('NDI_SANDBOX_PARTY_ID', '1220000'),
        'article_id' => env('NDI_SANDBOX_ARTICLE_ID', '4765205551649102'),
        'quantity' => (int) env('NDI_SANDBOX_ORDER_QUANTITY', 2),
        'stock_quantity' => (int) env('NDI_SANDBOX_STOCK_QUANTITY', 2),
        'price_minor' => (int) env('NDI_SANDBOX_PRICE_MINOR', 1000),
    ],
];
