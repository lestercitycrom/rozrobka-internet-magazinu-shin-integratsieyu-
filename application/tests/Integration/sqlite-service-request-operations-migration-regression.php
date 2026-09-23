<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

$root = dirname(__DIR__, 2);
$databasePath = $root.'/storage/framework/testing/issue-81-existing-data.sqlite';
$migrationName = '2026_09_23_000000_add_service_request_version_and_events';

is_dir(dirname($databasePath)) || mkdir(dirname($databasePath), 0777, true);
is_file($databasePath) && unlink($databasePath);
touch($databasePath);

foreach ([
    'APP_ENV' => 'testing',
    'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    'CACHE_STORE' => 'array',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $databasePath,
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
] as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function operationsMigrationAssert(bool $condition, string $message, mixed $actual = null): void
{
    if ($condition) {
        return;
    }

    $suffix = func_num_args() >= 3 ? ' Actual: '.var_export($actual, true) : '';
    throw new RuntimeException($message.$suffix);
}

/** @param array<string, mixed> $arguments */
function operationsMigrationArtisan(string $command, array $arguments = []): void
{
    $exitCode = Artisan::call($command, $arguments);
    operationsMigrationAssert($exitCode === 0, "Artisan command {$command} failed.\n".Artisan::output(), $exitCode);
}

try {
    $baseline = array_values(array_filter(
        glob(database_path('migrations/*.php')) ?: [],
        static fn (string $path): bool => basename($path) !== '2026_09_23_000000_add_service_request_version_and_events.php',
    ));

    operationsMigrationArtisan('migrate:fresh', [
        '--path' => $baseline,
        '--realpath' => true,
        '--force' => true,
    ]);
    operationsMigrationAssert(! Schema::hasColumn('service_requests', 'operation_version'), 'Baseline unexpectedly has operation_version.');
    operationsMigrationAssert(! Schema::hasTable('service_request_events'), 'Baseline unexpectedly has service_request_events.');

    $vehicles = json_encode([
        ['vehicle' => 'Volvo XC60', 'registration_number' => 'EV81001', 'storage_plan' => 'renew', 'storage_number' => '00042-A'],
    ], JSON_THROW_ON_ERROR);
    $requestId = (int) DB::table('service_requests')->insertGetId([
        'reference' => 'SR-81-PRE-EXISTING',
        'submission_token' => (string) Str::uuid(),
        'locale' => 'nb',
        'source' => 'public',
        'service_slug' => 'dekkskift-tromso',
        'fulfilment' => 'workshop',
        'vehicles' => $vehicles,
        'name' => 'Existing customer snapshot',
        'email' => null,
        'phone' => '91 23 45 67',
        'phone_normalized' => '4791234567',
        'status' => 'new',
        'admin_note' => 'Existing internal note',
        'consent_at' => '2026-09-22 12:00:00',
        'created_at' => '2026-09-22 12:00:00',
        'updated_at' => '2026-09-22 12:00:00',
    ]);
    $before = (array) DB::table('service_requests')->find($requestId);

    operationsMigrationArtisan('migrate', ['--force' => true]);

    operationsMigrationAssert(Schema::hasColumn('service_requests', 'operation_version'), 'operation_version was not added.');
    operationsMigrationAssert(Schema::hasTable('service_request_events'), 'service_request_events was not created.');
    $after = (array) DB::table('service_requests')->find($requestId);
    foreach (['reference', 'vehicles', 'name', 'email', 'phone', 'phone_normalized', 'status', 'admin_note', 'consent_at', 'created_at', 'updated_at'] as $column) {
        operationsMigrationAssert($after[$column] === $before[$column], "Existing request field {$column} changed during migration.", $after[$column]);
    }
    operationsMigrationAssert((int) $after['operation_version'] === 1, 'Existing request did not receive version 1.', $after['operation_version']);
    operationsMigrationAssert(DB::table('service_request_events')->count() === 0, 'Migration invented audit events for existing data.');

    Schema::dropIfExists('service_request_events');
    $migration = require database_path('migrations/'.$migrationName.'.php');
    $migration->up();

    operationsMigrationAssert(Schema::hasTable('service_request_events'), 'Migration did not resume after a partial deployment.');
    operationsMigrationAssert((int) DB::table('service_requests')->where('id', $requestId)->value('operation_version') === 1, 'Partial-deployment retry changed the existing version.');
    operationsMigrationAssert(DB::table('service_request_events')->count() === 0, 'Partial-deployment retry invented audit history.');
    operationsMigrationAssert(DB::table('migrations')->where('migration', $migrationName)->count() === 1, 'Migration ledger entry is missing or duplicated.');

    echo json_encode([
        'status' => 'ok',
        'existing_request_id' => $requestId,
        'operation_version' => 1,
        'events' => 0,
        'partial_retry' => 'ok',
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL;
} finally {
    DB::disconnect();
    is_file($databasePath) && unlink($databasePath);
}
