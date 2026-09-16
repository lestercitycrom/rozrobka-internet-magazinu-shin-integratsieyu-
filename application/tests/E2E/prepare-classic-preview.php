<?php

use App\Modules\Catalog\Models\SupplierSku;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Input\ArgvInput;

$normalizePath = static function (string $path): string {
    return rtrim(str_replace('\\', '/', $path), '/');
};

$abort = static function (string $message): never {
    fwrite(STDERR, "Classic preview fixture refused: {$message}\n");
    exit(1);
};

if (PHP_SAPI !== 'cli') {
    $abort('the bootstrap helper is CLI-only.');
}

if ($argc !== 2 || ! in_array($argv[1], ['modern', 'classic'], true)) {
    $abort('usage is prepare-classic-preview.php modern|classic.');
}

$profile = $argv[1];
$root = dirname(__DIR__, 2);
$relativeRoot = "tmp/classic-preview/ci/{$profile}";
$ownedRoot = "{$root}/{$relativeRoot}";
$databasePath = "{$ownedRoot}/database.sqlite";
$storagePath = "{$ownedRoot}/storage";
$cachePath = "{$ownedRoot}/cache";
$port = $profile === 'modern' ? 8046 : 8047;

$expectedEnvironment = [
    'CLASSIC_PREVIEW_BOOTSTRAP' => '1',
    'APP_ENV' => 'testing',
    'APP_URL' => "http://127.0.0.1:{$port}",
    'DB_CONNECTION' => 'sqlite',
    'DB_URL' => '',
    'DB_DATABASE' => $normalizePath($databasePath),
    'LARAVEL_STORAGE_PATH' => $normalizePath($storagePath),
    'VIEW_COMPILED_PATH' => $normalizePath("{$storagePath}/framework/views"),
    'APP_CONFIG_CACHE' => "{$relativeRoot}/cache/config.php",
    'APP_EVENTS_CACHE' => "{$relativeRoot}/cache/events.php",
    'APP_PACKAGES_CACHE' => "{$relativeRoot}/cache/packages.php",
    'APP_ROUTES_CACHE' => "{$relativeRoot}/cache/routes.php",
    'APP_SERVICES_CACHE' => "{$relativeRoot}/cache/services.php",
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'file',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
    'NDI_CATALOG_MODE' => 'snapshot',
    'NDI_SANDBOX_CATALOG_ENABLED' => 'true',
    'STOREFRONT_RU_PREVIEW_ENABLED' => 'true',
    'STOREFRONT_DESIGN_PROFILE' => $profile,
];

foreach ($expectedEnvironment as $name => $expectedValue) {
    $actualValue = getenv($name);
    $comparisonValue = in_array($name, ['DB_DATABASE', 'LARAVEL_STORAGE_PATH', 'VIEW_COMPILED_PATH'], true)
        ? $normalizePath((string) $actualValue)
        : $actualValue;

    if ($comparisonValue !== $expectedValue) {
        $abort("{$name} does not match the isolated {$profile} fixture contract.");
    }
}

$disabledEnvironmentFlags = [
    'CUSTOMER_EMAIL_DELIVERY_ENABLED',
    'NDI_C1_TEST_MODE',
    'NDI_FULFILMENT_READ_ENABLED',
    'NDI_INQUIRY_ENABLED',
    'NDI_MEDIA_SYNC_ENABLED',
    'NDI_ORDER_ENABLED',
    'NDI_SANDBOX_INQUIRY_ENABLED',
    'NDI_SANDBOX_ORDER_ENABLED',
    'OUTBOX_NOTION_ENABLED',
    'OUTBOX_TELEGRAM_ENABLED',
    'PAYMENTS_ENABLED',
    'VEHICLE_FITMENT_ENABLED',
];

foreach ($disabledEnvironmentFlags as $name) {
    if (getenv($name) !== 'false') {
        $abort("{$name} must be explicitly false.");
    }
}

$assertSafeDirectory = static function (string $path, string $expectedParent) use ($abort, $normalizePath): void {
    if (is_link($path)) {
        $abort('an owned fixture directory is a symbolic link.');
    }

    if (file_exists($path) && ! is_dir($path)) {
        $abort('an owned fixture directory path is not a directory.');
    }

    if (! is_dir($path) && ! mkdir($path, 0770) && ! is_dir($path)) {
        $abort('an owned fixture directory could not be created.');
    }

    $resolvedPath = realpath($path);
    $resolvedParent = realpath($expectedParent);

    if ($resolvedPath === false || $resolvedParent === false) {
        $abort('an owned fixture directory could not be resolved.');
    }

    $normalizedPath = $normalizePath($resolvedPath);
    $normalizedParent = $normalizePath($resolvedParent);

    if ($normalizedPath !== $normalizedParent && ! str_starts_with($normalizedPath, "{$normalizedParent}/")) {
        $abort('an owned fixture directory escapes its expected parent.');
    }
};

$tmpRoot = "{$root}/tmp";
$previewRoot = "{$tmpRoot}/classic-preview";
$ciRoot = "{$previewRoot}/ci";

$assertSafeDirectory($tmpRoot, $root);
$assertSafeDirectory($previewRoot, $tmpRoot);
$assertSafeDirectory($ciRoot, $previewRoot);
$assertSafeDirectory($ownedRoot, $ciRoot);

$ownedRootReal = realpath($ownedRoot);

if ($ownedRootReal === false || $normalizePath($ownedRootReal) !== $normalizePath($ownedRoot)) {
    $abort('the selected profile root did not resolve to the exact owned path.');
}

$ensureOwnedDirectory = static function (string $relativePath) use (
    $abort,
    $assertSafeDirectory,
    $ownedRoot,
): void {
    $currentPath = $ownedRoot;

    foreach (explode('/', $relativePath) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            $abort('an invalid owned fixture directory was requested.');
        }

        $parentPath = $currentPath;
        $currentPath .= "/{$segment}";
        $assertSafeDirectory($currentPath, $parentPath);
    }
};

foreach ([
    'cache',
    'storage/app/private',
    'storage/app/public',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
] as $directory) {
    $ensureOwnedDirectory($directory);
}

foreach (['config.php', 'events.php', 'packages.php', 'routes.php', 'services.php'] as $cacheFile) {
    $path = "{$cachePath}/{$cacheFile}";

    if (is_link($path) || file_exists($path)) {
        $abort('the profile cache must be empty before fixture bootstrap.');
    }
}

if (is_link($databasePath)) {
    $abort('the fixture database is a symbolic link.');
}

if (file_exists($databasePath) && ! is_file($databasePath)) {
    $abort('the fixture database path is not a regular file.');
}

if (is_file($databasePath) && ! unlink($databasePath)) {
    $abort('the exact owned fixture database could not be recreated.');
}

if (! touch($databasePath)) {
    $abort('the fixture database could not be created.');
}

$databaseReal = realpath($databasePath);

if ($databaseReal === false || $normalizePath($databaseReal) !== $normalizePath($databasePath)) {
    $abort('the fixture database escapes the exact owned path.');
}

require $root.'/vendor/autoload.php';

try {
    $app = require_once $root.'/bootstrap/app.php';
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();

    $effectiveDatabase = realpath((string) config('database.connections.sqlite.database'));
    $effectiveStorage = realpath($app->storagePath());
    $effectiveViews = realpath((string) config('view.compiled'));
    $effectiveConfigCacheDirectory = realpath(dirname($app->getCachedConfigPath()));

    if (! $app->environment('testing')) {
        throw new RuntimeException('the booted application is not in the testing environment.');
    }

    if (config('database.default') !== 'sqlite'
        || $effectiveDatabase === false
        || $normalizePath($effectiveDatabase) !== $normalizePath($databasePath)) {
        throw new RuntimeException('the booted application is not using the owned SQLite database.');
    }

    if ($effectiveStorage === false || $normalizePath($effectiveStorage) !== $normalizePath($storagePath)) {
        throw new RuntimeException('the booted application is not using the owned storage path.');
    }

    if ($effectiveViews === false || $normalizePath($effectiveViews) !== $normalizePath("{$storagePath}/framework/views")) {
        throw new RuntimeException('compiled views are not isolated to the owned storage path.');
    }

    if ($effectiveConfigCacheDirectory === false || $normalizePath($effectiveConfigCacheDirectory) !== $normalizePath($cachePath)) {
        throw new RuntimeException('the Laravel cache path is not isolated to the selected profile.');
    }

    if (config('storefront.design_profile') !== $profile
        || config('storefront.ru_preview_enabled') !== true
        || config('ndi.catalog_mode') !== 'snapshot') {
        throw new RuntimeException('the effective storefront fixture profile is not deterministic.');
    }

    $disabledConfigGates = [
        'customer_communications.delivery_enabled',
        'ndi.media_sync_enabled',
        'ndi_api.c1_test_mode',
        'ndi_api.fulfilment_read_enabled',
        'ndi_api.inquiry_enabled',
        'ndi_api.order_enabled',
        'ndi_api.sandbox.inquiry_enabled',
        'ndi_api.sandbox.order_enabled',
        'outbox.notion_enabled',
        'outbox.telegram_enabled',
        'payments.enabled',
        'vehicle_fitment.enabled',
    ];

    foreach ($disabledConfigGates as $key) {
        if (config($key) !== false) {
            throw new RuntimeException("the {$key} integration gate must remain disabled.");
        }
    }

    if (config('ndi_api.sandbox.enabled') !== true) {
        throw new RuntimeException('the local deterministic catalogue fixture must be enabled.');
    }

    if (config('cache.default') !== 'array'
        || config('session.driver') !== 'file'
        || config('queue.default') !== 'sync'
        || config('mail.default') !== 'array') {
        throw new RuntimeException('the effective runtime drivers are not CI-safe.');
    }

    $migrateStatus = $kernel->call('migrate:fresh', ['--force' => true]);

    if ($migrateStatus !== 0) {
        throw new RuntimeException('migrate:fresh failed.');
    }

    $seedStatus = $kernel->call('db:seed', [
        '--class' => 'Database\\Seeders\\E2eStorefrontSeeder',
        '--force' => true,
    ]);

    if ($seedStatus !== 0) {
        throw new RuntimeException('E2eStorefrontSeeder failed.');
    }

    if (! SupplierSku::query()->where('external_sku', 'NDI-TEST-4765205551649102')->exists()) {
        throw new RuntimeException('the deterministic test SKU was not seeded.');
    }

    fwrite(STDOUT, "Classic preview CI fixture ready: {$profile}.\n");
} catch (Throwable $exception) {
    $abort($exception instanceof RuntimeException
        ? $exception->getMessage()
        : 'Laravel bootstrap or fixture preparation failed.');
} finally {
    if (isset($kernel)) {
        $kernel->terminate(new ArgvInput, 0);
    }
}
