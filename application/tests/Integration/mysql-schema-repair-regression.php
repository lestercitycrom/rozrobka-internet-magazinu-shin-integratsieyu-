<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const SCHEMA_REPAIR_PREREQUISITE = '2026_09_12_000050_widen_storefront_settings_url_columns';
const SCHEMA_REPAIR_COMPOSITION = '2026_09_12_000100_add_customer_homepage_composition_fields';
const STAGING_MYSQL_SQL_MODE = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

function schemaRepairAssert(bool $condition, string $message, mixed $actual = null): void
{
    if ($condition) {
        return;
    }

    $suffix = func_num_args() >= 3 ? ' Actual: '.var_export($actual, true) : '';

    throw new RuntimeException($message.$suffix);
}

/** @param array<string, mixed> $arguments */
function schemaRepairArtisan(string $command, array $arguments = []): string
{
    $exitCode = Artisan::call($command, $arguments);
    $output = Artisan::output();

    schemaRepairAssert($exitCode === 0, "Artisan command {$command} failed.\n{$output}", $exitCode);

    return $output;
}

/** @return list<string> */
function schemaRepairBaselineMigrations(): array
{
    $excluded = [
        SCHEMA_REPAIR_PREREQUISITE.'.php',
        SCHEMA_REPAIR_COMPOSITION.'.php',
    ];

    return array_values(array_filter(
        glob(database_path('migrations/*.php')) ?: [],
        static fn (string $path): bool => ! in_array(basename($path), $excluded, true),
    ));
}

function schemaRepairFreshBaseline(): void
{
    schemaRepairArtisan('migrate:fresh', [
        '--path' => schemaRepairBaselineMigrations(),
        '--realpath' => true,
        '--force' => true,
    ]);

    schemaRepairAssertBaselineShape();
}

function schemaRepairRunAllMigrations(): void
{
    schemaRepairArtisan('migrate', ['--force' => true]);
}

function schemaRepairAssertBaselineShape(): void
{
    schemaRepairAssert(
        count(Schema::getColumnListing('storefront_settings')) === 132,
        'The pre-repair storefront_settings schema no longer matches the incident baseline.',
        count(Schema::getColumnListing('storefront_settings')),
    );

    $status = (array) DB::selectOne("SHOW TABLE STATUS LIKE 'storefront_settings'");
    schemaRepairAssert(
        strtolower((string) ($status['Row_format'] ?? '')) === 'dynamic',
        'The regression requires the same Dynamic row format as staging.',
        $status['Row_format'] ?? null,
    );
}

/** @return array<string, array<string, mixed>> */
function schemaRepairUrlMetadata(): array
{
    $rows = DB::select(
        <<<'SQL'
            SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT,
                   CHARACTER_SET_NAME, COLLATION_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'storefront_settings'
              AND COLUMN_NAME IN ('hero_primary_url', 'hero_secondary_url', 'map_url', 'map_embed_url')
            ORDER BY COLUMN_NAME
            SQL,
    );

    $metadata = [];

    foreach ($rows as $row) {
        $values = (array) $row;
        $metadata[(string) $values['COLUMN_NAME']] = $values;
    }

    return $metadata;
}

/** @return array<string, int> */
function schemaRepairTableCounts(): array
{
    $tables = DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    $counts = [];

    foreach ($tables as $table) {
        $name = (string) array_values((array) $table)[0];

        if ($name === 'migrations') {
            continue;
        }

        schemaRepairAssert(
            preg_match('/^[a-zA-Z0-9_]+$/D', $name) === 1,
            'Unexpected table identifier in isolated test database.',
            $name,
        );

        $counts[$name] = (int) DB::table($name)->count();
    }

    ksort($counts);

    return $counts;
}

/** @return array<string, mixed> */
function schemaRepairInsertFixtures(): array
{
    $now = '2026-09-13 00:00:00';
    $settings = [
        'hero_primary_url' => null,
        'hero_secondary_url' => '',
        'map_url' => str_repeat('a', 2048),
        'map_embed_url' => str_repeat('🚗', 2048),
        'phone_display' => '+47 000 00 000',
        'created_at' => $now,
        'updated_at' => $now,
    ];
    $settingsId = (int) DB::table('storefront_settings')->insertGetId($settings);

    $partnerId = (int) DB::table('homepage_business_partners')->insertGetId([
        'name' => 'Schema repair partner',
        'website_url' => 'https://example.test/'.str_repeat('ø', 1024),
        'sort_order' => 7,
        'publication_status' => 'draft',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $priceId = (int) DB::table('public_service_prices')->insertGetId([
        'code' => 'schema-repair-price',
        'category_code' => 'test',
        'category_title_nb' => 'Test',
        'category_title_ru' => 'Тест',
        'title_nb' => 'Schema repair',
        'title_ru' => 'Проверка схемы',
        'description_nb' => '',
        'description_ru' => 'Сохранить данные',
        'price_minor' => 12345,
        'currency' => 'NOK',
        'is_active' => false,
        'sort_order' => 9,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $outboxId = '01JSCHEMAREPAIR00000000000';
    DB::table('outbox_messages')->insert([
        'id' => $outboxId,
        'type' => 'schema.repair.audit',
        'aggregate_type' => 'test',
        'aggregate_id' => 'schema-repair',
        'idempotency_key' => 'schema-repair-outbox',
        'payload_encrypted' => 'synthetic-audit-payload',
        'status' => 'pending',
        'attempts' => 0,
        'available_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return [
        'settings_id' => $settingsId,
        'settings' => $settings,
        'partner_id' => $partnerId,
        'price_id' => $priceId,
        'outbox_id' => $outboxId,
    ];
}

/** @param array<string, mixed> $fixtures */
function schemaRepairAssertFixtures(array $fixtures): void
{
    $settings = (array) DB::table('storefront_settings')->find($fixtures['settings_id']);

    foreach ($fixtures['settings'] as $column => $expected) {
        schemaRepairAssert(
            array_key_exists($column, $settings) && $settings[$column] === $expected,
            "storefront_settings.{$column} changed during schema repair.",
            $settings[$column] ?? null,
        );
    }

    $partner = (array) DB::table('homepage_business_partners')->find($fixtures['partner_id']);
    schemaRepairAssert($partner['name'] === 'Schema repair partner', 'Partner data was not preserved.', $partner);
    schemaRepairAssert(mb_strlen((string) $partner['website_url']) === 1045, 'Partner multibyte URL was not preserved.', $partner['website_url']);

    $price = (array) DB::table('public_service_prices')->find($fixtures['price_id']);
    schemaRepairAssert($price['price_minor'] === 12345, 'Price data was not preserved.', $price);
    schemaRepairAssert($price['description_ru'] === 'Сохранить данные', 'Multibyte price text was not preserved.', $price);

    $outbox = (array) DB::table('outbox_messages')->where('id', $fixtures['outbox_id'])->first();
    schemaRepairAssert($outbox['payload_encrypted'] === 'synthetic-audit-payload', 'Audit/outbox row was not preserved.', $outbox);
}

/** @param array<string, array<string, mixed>> $before */
function schemaRepairAssertUrlConversion(array $before): void
{
    $after = schemaRepairUrlMetadata();

    schemaRepairAssert(array_keys($before) === array_keys($after), 'URL column set changed during conversion.');

    foreach ($before as $column => $definition) {
        schemaRepairAssert(strtolower((string) $definition['DATA_TYPE']) === 'varchar', "{$column} baseline type is not VARCHAR.", $definition);
        schemaRepairAssert(strtolower((string) $definition['COLUMN_TYPE']) === 'varchar(2048)', "{$column} baseline length is not 2048.", $definition);
        schemaRepairAssert(strtolower((string) $after[$column]['DATA_TYPE']) === 'text', "{$column} was not widened to TEXT.", $after[$column]);

        foreach (['IS_NULLABLE', 'COLUMN_DEFAULT', 'CHARACTER_SET_NAME', 'COLLATION_NAME'] as $attribute) {
            schemaRepairAssert(
                $after[$column][$attribute] === $definition[$attribute],
                "{$column} {$attribute} changed during conversion.",
                ['before' => $definition[$attribute], 'after' => $after[$column][$attribute]],
            );
        }
    }
}

function schemaRepairAssertFinalSchema(): void
{
    $settingsColumns = [
        'hotel_video_media_asset_id',
        'customer_homepage_composition_manifest',
        'mobile_details_title_nb', 'mobile_details_body_1_nb', 'mobile_details_body_2_nb', 'process_cta_nb', 'business_cta_nb', 'business_partners_heading_nb', 'contact_map_note_nb',
        'mobile_details_title_ru', 'mobile_details_body_1_ru', 'mobile_details_body_2_ru', 'process_cta_ru', 'business_cta_ru', 'business_partners_heading_ru', 'contact_map_note_ru',
    ];

    foreach ($settingsColumns as $column) {
        schemaRepairAssert(Schema::hasColumn('storefront_settings', $column), "Missing final storefront_settings.{$column}.");
    }

    foreach (['content_key', 'manifest_hash', 'deleted_at'] as $column) {
        schemaRepairAssert(Schema::hasColumn('homepage_business_partners', $column), "Missing final homepage_business_partners.{$column}.");
    }

    foreach (['manifest_hash', 'customer_homepage_composition_manifest'] as $column) {
        schemaRepairAssert(Schema::hasColumn('public_service_prices', $column), "Missing final public_service_prices.{$column}.");
    }

    $videoForeignKeys = array_values(array_filter(
        Schema::getForeignKeys('storefront_settings'),
        static fn (array $foreignKey): bool => $foreignKey['columns'] === ['hotel_video_media_asset_id'],
    ));
    schemaRepairAssert(count($videoForeignKeys) === 1, 'Expected exactly one hotel video foreign key.', $videoForeignKeys);
    schemaRepairAssert($videoForeignKeys[0]['foreign_table'] === 'media_assets', 'Hotel video foreign table is incorrect.', $videoForeignKeys[0]);
    schemaRepairAssert($videoForeignKeys[0]['foreign_columns'] === ['id'], 'Hotel video foreign column is incorrect.', $videoForeignKeys[0]);
    schemaRepairAssert(strtolower((string) $videoForeignKeys[0]['on_delete']) === 'set null', 'Hotel video delete action is incorrect.', $videoForeignKeys[0]);

    $contentKeyUnique = array_values(array_filter(
        Schema::getIndexes('homepage_business_partners'),
        static fn (array $index): bool => $index['columns'] === ['content_key'] && $index['unique'] && ! $index['primary'],
    ));
    schemaRepairAssert(count($contentKeyUnique) === 1, 'Expected exactly one content_key unique index.', $contentKeyUnique);

    foreach ([SCHEMA_REPAIR_PREREQUISITE, SCHEMA_REPAIR_COMPOSITION] as $migration) {
        schemaRepairAssert(
            DB::table('migrations')->where('migration', $migration)->count() === 1,
            "Migration ledger entry {$migration} is missing or duplicated.",
        );
    }
}

function schemaRepairReproduceLegacyFailure(): string
{
    $legacy = require base_path('tests/Fixtures/mysql-schema-repair/2026_09_12_000100_original_customer_homepage_composition_fields.php');

    try {
        $legacy->up();
    } catch (QueryException $exception) {
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = $exception->getMessage();

        schemaRepairAssert($driverCode === 1118, 'Legacy migration failed, but not with MySQL 1118.', $message);
        schemaRepairAssert(str_contains($message, 'mobile_details_body_2_ru'), 'Legacy failure occurred at an unexpected column.', $message);
        schemaRepairAssert(str_contains(strtolower($message), 'row size too large'), 'Legacy failure reason is not the row-size limit.', $message);

        return $message;
    }

    throw new RuntimeException('The original migration unexpectedly succeeded; MySQL 1118 was not reproduced.');
}

function schemaRepairAssertCurrentPartialState(): void
{
    $present = [
        'hotel_video_media_asset_id',
        'customer_homepage_composition_manifest',
        'mobile_details_title_nb', 'mobile_details_body_1_nb', 'mobile_details_body_2_nb', 'process_cta_nb', 'business_cta_nb', 'business_partners_heading_nb', 'contact_map_note_nb',
        'mobile_details_title_ru', 'mobile_details_body_1_ru',
    ];
    $missing = [
        'mobile_details_body_2_ru', 'process_cta_ru', 'business_cta_ru', 'business_partners_heading_ru', 'contact_map_note_ru',
    ];

    foreach ($present as $column) {
        schemaRepairAssert(Schema::hasColumn('storefront_settings', $column), "Legacy partial state is missing {$column}.");
    }

    foreach ($missing as $column) {
        schemaRepairAssert(! Schema::hasColumn('storefront_settings', $column), "Legacy partial state unexpectedly contains {$column}.");
    }

    schemaRepairAssert(! Schema::hasColumn('homepage_business_partners', 'content_key'), 'Legacy failure advanced into partner changes.');
    schemaRepairAssert(! Schema::hasColumn('public_service_prices', 'manifest_hash'), 'Legacy failure advanced into price changes.');
    schemaRepairAssert(DB::table('migrations')->where('migration', SCHEMA_REPAIR_COMPOSITION)->doesntExist(), 'Failed legacy migration was recorded as complete.');
}

function schemaRepairScenarioLegacyPartialRecovery(): array
{
    schemaRepairFreshBaseline();
    $beforeUrls = schemaRepairUrlMetadata();
    $fixtures = schemaRepairInsertFixtures();
    $counts = schemaRepairTableCounts();
    $legacyError = schemaRepairReproduceLegacyFailure();
    schemaRepairAssertCurrentPartialState();

    schemaRepairRunAllMigrations();
    schemaRepairAssertFinalSchema();
    schemaRepairAssertUrlConversion($beforeUrls);
    schemaRepairAssertFixtures($fixtures);
    schemaRepairAssert(schemaRepairTableCounts() === $counts, 'Table row counts changed during partial recovery.', [
        'before' => $counts,
        'after' => schemaRepairTableCounts(),
    ]);

    schemaRepairRunAllMigrations();
    schemaRepairAssertFinalSchema();
    schemaRepairAssert(schemaRepairTableCounts() === $counts, 'Second migration run changed row counts.');

    return ['legacy_error' => $legacyError];
}

function schemaRepairScenarioOrdinaryUpdate(): void
{
    schemaRepairFreshBaseline();
    $beforeUrls = schemaRepairUrlMetadata();
    $fixtures = schemaRepairInsertFixtures();
    $counts = schemaRepairTableCounts();

    schemaRepairRunAllMigrations();
    schemaRepairAssertFinalSchema();
    schemaRepairAssertUrlConversion($beforeUrls);
    schemaRepairAssertFixtures($fixtures);
    schemaRepairAssert(schemaRepairTableCounts() === $counts, 'Ordinary update changed table row counts.');
}

function schemaRepairScenarioInterruptedLaterStep(): void
{
    schemaRepairFreshBaseline();
    $beforeUrls = schemaRepairUrlMetadata();
    $fixtures = schemaRepairInsertFixtures();
    $counts = schemaRepairTableCounts();
    $prerequisite = database_path('migrations/'.SCHEMA_REPAIR_PREREQUISITE.'.php');

    schemaRepairArtisan('migrate', [
        '--path' => [$prerequisite],
        '--realpath' => true,
        '--force' => true,
    ]);

    Schema::table('storefront_settings', function (Blueprint $table): void {
        $table->char('hotel_video_media_asset_id', 26)->nullable();
        $table->foreign('hotel_video_media_asset_id')->references('id')->on('media_assets')->nullOnDelete();
        $table->json('customer_homepage_composition_manifest')->nullable();
        $table->text('mobile_details_title_nb')->nullable();
    });
    Schema::table('homepage_business_partners', function (Blueprint $table): void {
        $table->string('content_key', 160)->nullable();
        $table->char('manifest_hash', 64)->nullable();
    });
    Schema::table('public_service_prices', function (Blueprint $table): void {
        $table->char('manifest_hash', 64)->nullable();
    });

    schemaRepairRunAllMigrations();
    schemaRepairAssertFinalSchema();
    schemaRepairAssertUrlConversion($beforeUrls);
    schemaRepairAssertFixtures($fixtures);
    schemaRepairAssert(schemaRepairTableCounts() === $counts, 'Interrupted-step recovery changed table row counts.');
}

function schemaRepairScenarioCompositionAlreadyRecorded(): void
{
    schemaRepairFreshBaseline();
    $beforeUrls = schemaRepairUrlMetadata();
    $batch = ((int) DB::table('migrations')->max('batch')) + 1;

    DB::table('migrations')->insert([
        'migration' => SCHEMA_REPAIR_COMPOSITION,
        'batch' => $batch,
    ]);

    schemaRepairRunAllMigrations();
    schemaRepairAssertUrlConversion($beforeUrls);
    schemaRepairAssert(
        DB::table('migrations')->where('migration', SCHEMA_REPAIR_PREREQUISITE)->count() === 1,
        'Older prerequisite was not applied when the later migration was already recorded.',
    );
}

function schemaRepairScenarioConflictFailsClosed(): string
{
    schemaRepairFreshBaseline();
    $prerequisite = database_path('migrations/'.SCHEMA_REPAIR_PREREQUISITE.'.php');

    schemaRepairArtisan('migrate', [
        '--path' => [$prerequisite],
        '--realpath' => true,
        '--force' => true,
    ]);

    Schema::table('storefront_settings', function (Blueprint $table): void {
        $table->string('mobile_details_title_nb')->nullable();
    });

    try {
        $exitCode = Artisan::call('migrate', ['--force' => true]);
        $message = Artisan::output();
    } catch (RuntimeException $exception) {
        $exitCode = 1;
        $message = $exception->getMessage();
    }

    schemaRepairAssert($exitCode !== 0, 'A conflicting column definition was silently accepted.');
    schemaRepairAssert(
        str_contains($message, 'Incompatible schema for storefront_settings.mobile_details_title_nb'),
        'Conflict failure did not identify the exact incompatible column.',
        $message,
    );
    schemaRepairAssert(
        DB::table('migrations')->where('migration', SCHEMA_REPAIR_COMPOSITION)->doesntExist(),
        'Conflicting composition migration was recorded as complete.',
    );

    return $message;
}

try {
    $connection = DB::connection();
    $databaseName = (string) $connection->getDatabaseName();

    schemaRepairAssert($connection->getDriverName() === 'mysql', 'This regression must run against real MySQL.', $connection->getDriverName());
    schemaRepairAssert(app()->environment('testing'), 'This destructive regression is restricted to APP_ENV=testing.', app()->environment());
    schemaRepairAssert(str_contains(strtolower($databaseName), 'test'), 'The isolated database name must contain "test".', $databaseName);
    schemaRepairAssert(preg_match('/^[a-zA-Z0-9_]+$/D', $databaseName) === 1, 'The isolated database name must be a simple identifier.', $databaseName);

    DB::statement("ALTER DATABASE `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $metadata = (array) DB::selectOne(
        <<<'SQL'
            SELECT VERSION() AS server_version,
                   @@SESSION.sql_mode AS sql_mode,
                   @@character_set_database AS database_character_set,
                   @@collation_database AS database_collation,
                   @@innodb_strict_mode AS innodb_strict_mode
            SQL,
    );

    schemaRepairAssert(str_starts_with((string) $metadata['server_version'], '8.0.'), 'The regression must run on MySQL 8.0.', $metadata);
    schemaRepairAssert($metadata['sql_mode'] === STAGING_MYSQL_SQL_MODE, 'The MySQL SQL mode does not match staging.', $metadata);
    schemaRepairAssert($metadata['database_character_set'] === 'utf8mb4', 'The database character set does not match staging.', $metadata);
    schemaRepairAssert($metadata['database_collation'] === 'utf8mb4_unicode_ci', 'The database collation does not match staging.', $metadata);
    schemaRepairAssert((int) $metadata['innodb_strict_mode'] === 1, 'InnoDB strict mode must match staging.', $metadata);

    $partial = schemaRepairScenarioLegacyPartialRecovery();
    schemaRepairScenarioOrdinaryUpdate();
    schemaRepairScenarioInterruptedLaterStep();
    schemaRepairScenarioCompositionAlreadyRecorded();
    $conflict = schemaRepairScenarioConflictFailsClosed();
    schemaRepairArtisan('migrate:fresh', ['--force' => true]);
    schemaRepairAssertFinalSchema();

    $tableStatus = (array) DB::selectOne("SHOW TABLE STATUS LIKE 'storefront_settings'");

    fwrite(STDOUT, json_encode([
        'status' => 'passed',
        'server' => $metadata,
        'storefront_row_format' => $tableStatus['Row_format'] ?? null,
        'verified' => [
            'legacy_mysql_1118_reproduced' => str_contains(strtolower($partial['legacy_error']), 'row size too large'),
            'fresh_install' => true,
            'ordinary_update' => true,
            'current_partial_state_recovery' => true,
            'later_step_interruption_recovery' => true,
            'repeat_is_idempotent' => true,
            'later_migration_already_recorded' => true,
            'synthetic_data_preserved' => true,
            'conflict_fails_closed' => str_contains($conflict, 'mobile_details_title_nb'),
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, "MySQL schema repair regression failed: {$exception->getMessage()}\n");
    exit(1);
}
