<?php

return [
    'supplier_code' => 'ndi',
    'parser_version' => env('NDI_PARSER_VERSION', '1.1.0'),
    'raw_disk' => env('NDI_RAW_DISK', 'local'),
    'raw_prefix' => env('NDI_RAW_PREFIX', 'ndi/raw'),
    'catalog_currency' => env('NDI_CATALOG_CURRENCY'),
    // Only NDI's documented image handler is admitted; arbitrary feed URLs are never fetched.
    'media_hosts' => array_values(array_filter(explode(',', (string) env('NDI_MEDIA_HOSTS', 'files.ndias.com')))),
    'media_max_bytes' => (int) env('NDI_MEDIA_MAX_BYTES', 5 * 1024 * 1024),
    'media_max_pixels' => (int) env('NDI_MEDIA_MAX_PIXELS', 20_000_000),
    'media_retry_after_minutes' => (int) env('NDI_MEDIA_RETRY_AFTER_MINUTES', 60),
    'media_retry_max_minutes' => (int) env('NDI_MEDIA_RETRY_MAX_MINUTES', 24 * 60),
    'media_refresh_days' => (int) env('NDI_MEDIA_REFRESH_DAYS', 7),
    'media_lock_seconds' => (int) env('NDI_MEDIA_LOCK_SECONDS', 15 * 60),
    // NDI's national availability field is capped at 40 in the supplied feed.
    'stock_national_cap' => (int) env('NDI_STOCK_NATIONAL_CAP', 40),
];
