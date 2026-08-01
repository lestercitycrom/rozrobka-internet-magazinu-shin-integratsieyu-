<?php

return [
    'notion_enabled' => (bool) env('OUTBOX_NOTION_ENABLED', false),
    'telegram_enabled' => (bool) env('OUTBOX_TELEGRAM_ENABLED', false),
    'timeout' => (int) env('OUTBOX_TIMEOUT_SECONDS', 5),
    'max_attempts' => (int) env('OUTBOX_MAX_ATTEMPTS', 5),
    'lock_timeout_seconds' => (int) env('OUTBOX_LOCK_TIMEOUT_SECONDS', 300),
    'notion' => [
        'token' => env('OUTBOX_NOTION_TOKEN'),
        'database_id' => env('OUTBOX_NOTION_DATABASE_ID'),
        'title_property' => env('OUTBOX_NOTION_TITLE_PROPERTY', 'Name'),
        'api_version' => env('OUTBOX_NOTION_API_VERSION', '2022-06-28'),
    ],
    'telegram' => [
        'bot_token' => env('OUTBOX_TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('OUTBOX_TELEGRAM_CHAT_ID'),
    ],
];
