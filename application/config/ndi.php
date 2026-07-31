<?php

return [
    'supplier_code' => 'ndi',
    'parser_version' => env('NDI_PARSER_VERSION', '1.0.0'),
    'raw_disk' => env('NDI_RAW_DISK', 'local'),
    'raw_prefix' => env('NDI_RAW_PREFIX', 'ndi/raw'),
    'catalog_currency' => env('NDI_CATALOG_CURRENCY'),
    // NDI's national availability field is capped at 40 in the supplied feed.
    'stock_national_cap' => (int) env('NDI_STOCK_NATIONAL_CAP', 40),
];
