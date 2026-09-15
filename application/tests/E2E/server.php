<?php

declare(strict_types=1);

$applicationRoot = dirname(__DIR__, 2);
$host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000';
$databasePath = getenv('DB_DATABASE');
if (! is_string($databasePath) || $databasePath === '') {
    $databasePath = $applicationRoot.'/database/e2e.sqlite';
}

$environment = [
    'APP_DEBUG' => 'false',
    'APP_ENV' => 'testing',
    'APP_URL' => 'http://'.$host,
    'ASSET_URL' => 'http://'.$host,
    'CACHE_STORE' => 'array',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $databasePath,
    'DB_URL' => '',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'file',
    'NDI_INQUIRY_ENABLED' => 'false',
    'NDI_ORDER_ENABLED' => 'false',
    'NDI_C1_TEST_MODE' => 'false',
    'NDI_SANDBOX_CATALOG_ENABLED' => 'true',
    'NDI_SANDBOX_INQUIRY_ENABLED' => 'false',
    'NDI_SANDBOX_ORDER_ENABLED' => 'false',
    'NDI_FULFILMENT_READ_ENABLED' => 'false',
    'NDI_MEDIA_SYNC_ENABLED' => 'false',
    'OUTBOX_NOTION_ENABLED' => 'false',
    'OUTBOX_TELEGRAM_ENABLED' => 'false',
    'PAYMENTS_ENABLED' => 'false',
    'STOREFRONT_RU_PREVIEW_ENABLED' => 'true',
    'VEHICLE_FITMENT_ENABLED' => 'false',
];

foreach ($environment as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

$publicPath = $applicationRoot.'/public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

if ($uri !== '/' && file_exists($publicPath.$uri)) {
    return false;
}

require $publicPath.'/index.php';
