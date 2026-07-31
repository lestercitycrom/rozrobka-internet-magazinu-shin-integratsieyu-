<?php

return [
    'notion_enabled' => (bool) env('OUTBOX_NOTION_ENABLED', false),
    'telegram_enabled' => (bool) env('OUTBOX_TELEGRAM_ENABLED', false),
    'timeout' => (int) env('OUTBOX_TIMEOUT_SECONDS', 5),
    'max_attempts' => (int) env('OUTBOX_MAX_ATTEMPTS', 5),
    'lock_timeout_seconds' => (int) env('OUTBOX_LOCK_TIMEOUT_SECONDS', 300),
];
