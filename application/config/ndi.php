<?php

return [
    'supplier_code' => 'ndi',
    // Snapshot is a non-production display mode; it never renews source data.
    'catalog_mode' => env('NDI_CATALOG_MODE', 'live'),
    'parser_version' => env('NDI_PARSER_VERSION', '1.1.0'),
    'raw_disk' => env('NDI_RAW_DISK', 'local'),
    'raw_prefix' => env('NDI_RAW_PREFIX', 'ndi/raw'),
    'catalog_currency' => env('NDI_CATALOG_CURRENCY'),
    'inbox_path' => env('NDI_INBOX_PATH'),
    // Supplier media work is optional and must be explicitly enabled per environment.
    'media_sync_enabled' => (bool) env('NDI_MEDIA_SYNC_ENABLED', false),
    'inbox_stable_seconds' => (int) env('NDI_INBOX_STABLE_SECONDS', 120),
    'stock_drop_guard_min_baseline' => (int) env('NDI_STOCK_DROP_GUARD_MIN_BASELINE', 50),
    'inbox_retry_backoff_minutes' => (int) env('NDI_INBOX_RETRY_BACKOFF_MINUTES', 10),
    'import_lock_seconds' => (int) env('NDI_IMPORT_LOCK_SECONDS', 1800),
    'stock_minimum_previous_ratio' => (float) env('NDI_STOCK_MINIMUM_PREVIOUS_RATIO', 0.9),
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
