<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\StorefrontSettingsController;
use App\Http\Requests\Admin\UpdateStorefrontSettingsRequest;
use Database\Seeders\CustomerHomepageReleaseSeeder;
use Database\Seeders\PublicServicePriceSeeder;
use Database\Seeders\RollbackCustomerHomepageReleaseSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const SCHEMA_REPAIR_PREREQUISITE = '2026_09_12_000050_widen_storefront_settings_url_columns';
const SCHEMA_REPAIR_COMPOSITION = '2026_09_12_000100_add_customer_homepage_composition_fields';
const STAGING_MYSQL_SQL_MODE = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
const STAGING_PRE_FAILURE_SCHEMA_SHA256 = '3d5af63006437507b182eb5f1bce9309561ba385d8bfa63728d85002aeefd72f';

function schemaRepairAssert(bool $condition, string $message, mixed $actual = null): void
{
    if ($condition) {
        return;
    }

    $suffix = func_num_args() >= 3 ? ' Actual: '.var_export($actual, true) : '';

    throw new RuntimeException($message.$suffix);
}

function schemaRepairCanonicalJson(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }

    if (! array_is_list($value)) {
        ksort($value);
    }

    foreach ($value as $key => $item) {
        $value[$key] = schemaRepairCanonicalJson($item);
    }

    return $value;
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

/**
 * The staging table evolved through an older migration history where these
 * short Norwegian labels already existed as VARCHAR(255). The current clean
 * migration creates them as TEXT, so the incident is reproducible only after
 * restoring the exact metadata-only historical shape captured from staging.
 */
function schemaRepairApplyHistoricalStagingShape(): void
{
    $historicalVarchars = [
        'chooser_kicker_nb',
        'chooser_title_nb',
        'chooser_mobile_title_nb',
        'chooser_workshop_title_nb',
        'chooser_store_kicker_nb',
        'chooser_store_title_nb',
        'trust_1_nb',
        'trust_2_nb',
        'trust_3_nb',
        'process_kicker_nb',
        'process_title_nb',
    ];

    foreach ($historicalVarchars as $column) {
        DB::statement(
            "ALTER TABLE `storefront_settings` MODIFY `{$column}` VARCHAR(255) CHARACTER SET `utf8mb4` COLLATE `utf8mb4_unicode_ci` NULL DEFAULT NULL"
        );
    }

    $rows = DB::select(
        <<<'SQL'
            SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT,
                   CHARACTER_SET_NAME, COLLATION_NAME, EXTRA, GENERATION_EXPRESSION
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'storefront_settings'
            ORDER BY ORDINAL_POSITION
            SQL,
    );
    $profile = array_map(static function (object $row): array {
        return array_map(
            static fn ($value): string => $value === null ? '<NULL>' : (string) $value,
            (array) $row,
        );
    }, $rows);
    $canonical = json_encode(
        $profile,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    );
    $actualHash = hash('sha256', $canonical);

    schemaRepairAssert(
        $actualHash === STAGING_PRE_FAILURE_SCHEMA_SHA256,
        'The metadata-only incident fixture does not match the captured staging pre-failure schema.',
        $actualHash,
    );
}

/** @return array<string, array<string, mixed>> */
function schemaRepairUrlMetadata(): array
{
    $rows = DB::select(
        <<<'SQL'
            SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT,
                   CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_COMMENT, EXTRA,
                   GENERATION_EXPRESSION
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

/** @return array<string, list<array<string, mixed>>> */
function schemaRepairContentKeyUniqueMetadata(): array
{
    $rows = DB::select(
        <<<'SQL'
            SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME,
                   SUB_PART, EXPRESSION, INDEX_TYPE, IS_VISIBLE
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'homepage_business_partners'
              AND INDEX_NAME <> 'PRIMARY'
              AND NON_UNIQUE = 0
            ORDER BY INDEX_NAME, SEQ_IN_INDEX
            SQL,
    );
    $allIndexes = [];

    foreach ($rows as $row) {
        $part = (array) $row;
        $allIndexes[(string) $part['INDEX_NAME']][] = $part;
    }

    return array_filter(
        $allIndexes,
        static function (array $parts, string $name): bool {
            if ($name === 'homepage_business_partners_content_key_unique') {
                return true;
            }

            foreach ($parts as $part) {
                $column = strtolower((string) ($part['COLUMN_NAME'] ?? ''));
                $expression = strtolower((string) ($part['EXPRESSION'] ?? ''));

                if ($column === 'content_key' || str_contains($expression, 'content_key')) {
                    return true;
                }
            }

            return false;
        },
        ARRAY_FILTER_USE_BOTH,
    );
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

/**
 * @param  array<string, mixed>  $fixtures
 * @param  list<string>  $ignoredSettingsColumns
 */
function schemaRepairAssertFixtures(array $fixtures, array $ignoredSettingsColumns = []): void
{
    $settings = (array) DB::table('storefront_settings')->find($fixtures['settings_id']);

    foreach ($fixtures['settings'] as $column => $expected) {
        if (in_array($column, $ignoredSettingsColumns, true)) {
            continue;
        }

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

        foreach (['IS_NULLABLE', 'COLUMN_DEFAULT', 'CHARACTER_SET_NAME', 'COLLATION_NAME', 'COLUMN_COMMENT'] as $attribute) {
            schemaRepairAssert(
                $after[$column][$attribute] === $definition[$attribute],
                "{$column} {$attribute} changed during conversion.",
                ['before' => $definition[$attribute], 'after' => $after[$column][$attribute]],
            );
        }

        schemaRepairAssert($after[$column]['EXTRA'] === '', "{$column} became a generated or otherwise special column.", $after[$column]);
        schemaRepairAssert($after[$column]['GENERATION_EXPRESSION'] === '', "{$column} gained a generation expression.", $after[$column]);
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
    schemaRepairAssert(strtolower((string) $videoForeignKeys[0]['foreign_schema']) === strtolower((string) DB::connection()->getDatabaseName()), 'Hotel video foreign schema is incorrect.', $videoForeignKeys[0]);
    schemaRepairAssert(in_array(strtolower((string) $videoForeignKeys[0]['on_update']), ['restrict', 'no action'], true), 'Hotel video update action is incorrect.', $videoForeignKeys[0]);
    schemaRepairAssert(strtolower((string) $videoForeignKeys[0]['on_delete']) === 'set null', 'Hotel video delete action is incorrect.', $videoForeignKeys[0]);

    $contentKeyUnique = schemaRepairContentKeyUniqueMetadata();
    schemaRepairAssert(count($contentKeyUnique) === 1, 'Expected exactly one content_key unique index.', $contentKeyUnique);
    $contentKeyUniqueParts = array_values($contentKeyUnique)[0];
    schemaRepairAssert(count($contentKeyUniqueParts) === 1, 'content_key unique index is composite.', $contentKeyUnique);
    $contentKeyUniquePart = $contentKeyUniqueParts[0];
    schemaRepairAssert((int) $contentKeyUniquePart['SEQ_IN_INDEX'] === 1, 'content_key unique index has an invalid sequence.', $contentKeyUnique);
    schemaRepairAssert(strtolower((string) $contentKeyUniquePart['COLUMN_NAME']) === 'content_key', 'content_key unique index targets the wrong column.', $contentKeyUnique);
    schemaRepairAssert($contentKeyUniquePart['SUB_PART'] === null, 'content_key unique index uses a prefix.', $contentKeyUnique);
    schemaRepairAssert($contentKeyUniquePart['EXPRESSION'] === null, 'content_key unique index uses an expression.', $contentKeyUnique);
    schemaRepairAssert(strtolower((string) $contentKeyUniquePart['INDEX_TYPE']) === 'btree', 'content_key unique index is not BTREE.', $contentKeyUnique);
    schemaRepairAssert(strtolower((string) $contentKeyUniquePart['IS_VISIBLE']) === 'yes', 'content_key unique index is not visible.', $contentKeyUnique);

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
    schemaRepairApplyHistoricalStagingShape();
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
    DB::statement(
        "ALTER TABLE `storefront_settings` MODIFY `hero_primary_url` VARCHAR(2048) CHARACTER SET `utf8mb4` COLLATE `utf8mb4_unicode_ci` NULL DEFAULT NULL COMMENT 'Customer navigation URL'"
    );
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
    $fixtures = schemaRepairInsertFixtures();
    $counts = schemaRepairTableCounts();
    $legacy = require base_path('tests/Fixtures/mysql-schema-repair/2026_09_12_000100_original_customer_homepage_composition_fields.php');

    $legacy->up();
    schemaRepairAssertFinalCompositionShapeWithoutLedger();
    $batch = ((int) DB::table('migrations')->max('batch')) + 1;

    DB::table('migrations')->insert([
        'migration' => SCHEMA_REPAIR_COMPOSITION,
        'batch' => $batch,
    ]);

    schemaRepairRunAllMigrations();
    schemaRepairAssertFinalSchema();
    schemaRepairAssertUrlConversion($beforeUrls);
    schemaRepairAssertFixtures($fixtures);
    schemaRepairAssert(schemaRepairTableCounts() === $counts, 'Applying the later-added prerequisite changed completed-composition row counts.');
    schemaRepairAssert(
        DB::table('migrations')->where('migration', SCHEMA_REPAIR_PREREQUISITE)->count() === 1,
        'Older prerequisite was not applied when the later migration was already recorded.',
    );
}

function schemaRepairAssertFinalCompositionShapeWithoutLedger(): void
{
    foreach ([
        'hotel_video_media_asset_id',
        'customer_homepage_composition_manifest',
        'mobile_details_title_nb', 'mobile_details_body_1_nb', 'mobile_details_body_2_nb', 'process_cta_nb', 'business_cta_nb', 'business_partners_heading_nb', 'contact_map_note_nb',
        'mobile_details_title_ru', 'mobile_details_body_1_ru', 'mobile_details_body_2_ru', 'process_cta_ru', 'business_cta_ru', 'business_partners_heading_ru', 'contact_map_note_ru',
    ] as $column) {
        schemaRepairAssert(Schema::hasColumn('storefront_settings', $column), "Completed legacy composition is missing storefront_settings.{$column}.");
    }

    foreach (['content_key', 'manifest_hash', 'deleted_at'] as $column) {
        schemaRepairAssert(Schema::hasColumn('homepage_business_partners', $column), "Completed legacy composition is missing homepage_business_partners.{$column}.");
    }

    foreach (['manifest_hash', 'customer_homepage_composition_manifest'] as $column) {
        schemaRepairAssert(Schema::hasColumn('public_service_prices', $column), "Completed legacy composition is missing public_service_prices.{$column}.");
    }
}

function schemaRepairScenarioInterruptedUrlConversion(): void
{
    schemaRepairFreshBaseline();
    $fixtures = schemaRepairInsertFixtures();
    $beforeUrls = schemaRepairUrlMetadata();
    $counts = schemaRepairTableCounts();

    foreach (['hero_primary_url', 'hero_secondary_url'] as $column) {
        DB::statement(
            "ALTER TABLE `storefront_settings` MODIFY `{$column}` TEXT CHARACTER SET `utf8mb4` COLLATE `utf8mb4_unicode_ci` NULL DEFAULT NULL"
        );
    }
    $alreadyConverted = schemaRepairUrlMetadata();

    schemaRepairRunAllMigrations();
    schemaRepairAssertFinalSchema();
    schemaRepairAssertUrlConversion($beforeUrls);
    $after = schemaRepairUrlMetadata();
    foreach (['hero_primary_url', 'hero_secondary_url'] as $column) {
        schemaRepairAssert(
            $after[$column] === $alreadyConverted[$column],
            "Already-correct TEXT definition {$column} was not a no-op.",
            ['before' => $alreadyConverted[$column], 'after' => $after[$column]],
        );
    }
    schemaRepairAssertFixtures($fixtures);
    schemaRepairAssert(schemaRepairTableCounts() === $counts, 'Interrupted URL conversion changed table row counts.');
}

function schemaRepairExpectMigrationFailure(string $expectedMessage): string
{
    try {
        $exitCode = Artisan::call('migrate', ['--force' => true]);
        $message = Artisan::output();
    } catch (Throwable $exception) {
        $exitCode = 1;
        $message = $exception->getMessage();
    }

    schemaRepairAssert($exitCode !== 0, 'An incompatible schema was silently accepted.');
    schemaRepairAssert(
        str_contains($message, $expectedMessage),
        'Schema conflict failure did not identify the expected incompatibility.',
        $message,
    );

    return $message;
}

function schemaRepairScenarioGeneratedUrlFailsClosed(): string
{
    schemaRepairFreshBaseline();
    DB::statement('ALTER TABLE `storefront_settings` DROP COLUMN `hero_primary_url`');
    DB::statement(
        'ALTER TABLE `storefront_settings` ADD `hero_primary_url` TEXT GENERATED ALWAYS AS (`hero_secondary_url`) VIRTUAL'
    );

    $message = schemaRepairExpectMigrationFailure('expected an ordinary nullable DEFAULT NULL column');
    schemaRepairAssert(
        DB::table('migrations')->where('migration', SCHEMA_REPAIR_PREREQUISITE)->doesntExist(),
        'Generated URL conflict was recorded as migrated.',
    );

    return $message;
}

function schemaRepairScenarioForeignKeyConflictFailsClosed(string $mode): string
{
    schemaRepairFreshBaseline();
    $prerequisite = database_path('migrations/'.SCHEMA_REPAIR_PREREQUISITE.'.php');
    schemaRepairArtisan('migrate', [
        '--path' => [$prerequisite],
        '--realpath' => true,
        '--force' => true,
    ]);
    Schema::table('storefront_settings', function (Blueprint $table): void {
        $table->char('hotel_video_media_asset_id', 26)->nullable();
    });

    $database = (string) DB::connection()->getDatabaseName();
    $foreignDatabase = $database.'_foreign_fixture';

    try {
        if ($mode === 'cross-schema') {
            DB::statement("DROP DATABASE IF EXISTS `{$foreignDatabase}`");
            DB::statement("CREATE DATABASE `{$foreignDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            DB::statement(
                "CREATE TABLE `{$foreignDatabase}`.`media_assets` (`id` CHAR(26) NOT NULL PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            DB::statement(
                "ALTER TABLE `storefront_settings` ADD CONSTRAINT `schema_repair_cross_database_fk` FOREIGN KEY (`hotel_video_media_asset_id`) REFERENCES `{$foreignDatabase}`.`media_assets` (`id`) ON DELETE SET NULL"
            );
        } else {
            DB::statement(
                'ALTER TABLE `storefront_settings` ADD CONSTRAINT `schema_repair_changed_update_fk` FOREIGN KEY (`hotel_video_media_asset_id`) REFERENCES `media_assets` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
            );
        }

        return schemaRepairExpectMigrationFailure('Incompatible foreign key for storefront_settings.hotel_video_media_asset_id');
    } finally {
        if ($mode === 'cross-schema') {
            DB::statement('ALTER TABLE `storefront_settings` DROP FOREIGN KEY `schema_repair_cross_database_fk`');
            DB::statement("DROP DATABASE IF EXISTS `{$foreignDatabase}`");
        }
    }
}

/** @return array{message: string, before: array<string, list<array<string, mixed>>>, after: array<string, list<array<string, mixed>>>} */
function schemaRepairScenarioUniqueIndexConflictFailsClosed(string $mode): array
{
    schemaRepairFreshBaseline();
    $prerequisite = database_path('migrations/'.SCHEMA_REPAIR_PREREQUISITE.'.php');
    schemaRepairArtisan('migrate', [
        '--path' => [$prerequisite],
        '--realpath' => true,
        '--force' => true,
    ]);
    Schema::table('homepage_business_partners', function (Blueprint $table): void {
        $table->string('content_key', 160)->nullable();
    });

    if ($mode === 'non-btree') {
        DB::statement('ALTER TABLE `homepage_business_partners` DROP FOREIGN KEY `homepage_business_partners_media_asset_id_foreign`');
        DB::statement('ALTER TABLE `homepage_business_partners` ENGINE = MEMORY');
        DB::statement(
            'CREATE UNIQUE INDEX `schema_repair_content_key_hash_unique` USING HASH ON `homepage_business_partners` (`content_key`)'
        );
    } elseif ($mode === 'prefix') {
        DB::statement(
            'CREATE UNIQUE INDEX `schema_repair_content_key_prefix_unique` ON `homepage_business_partners` (`content_key`(8))'
        );
    } elseif ($mode === 'expression') {
        DB::statement(
            'CREATE UNIQUE INDEX `schema_repair_content_key_expression_unique` ON `homepage_business_partners` ((lower(`content_key`)))'
        );
    } elseif ($mode === 'composite') {
        DB::statement(
            'CREATE UNIQUE INDEX `schema_repair_content_key_composite_unique` ON `homepage_business_partners` (`content_key`, `name`)'
        );
    } elseif ($mode === 'invisible') {
        DB::statement(
            'CREATE UNIQUE INDEX `schema_repair_content_key_invisible_unique` ON `homepage_business_partners` (`content_key`) INVISIBLE'
        );
    } else {
        throw new RuntimeException("Unknown unique-index conflict fixture: {$mode}.");
    }

    $before = schemaRepairContentKeyUniqueMetadata();
    $message = schemaRepairExpectMigrationFailure('Incompatible index for homepage_business_partners.content_key');
    $after = schemaRepairContentKeyUniqueMetadata();
    schemaRepairAssert($after === $before, 'Migration silently replaced or changed an incompatible content_key index.', [
        'before' => $before,
        'after' => $after,
    ]);

    return compact('message', 'before', 'after');
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

function schemaRepairApplyOperatorHeroEdit(string $value): void
{
    $request = UpdateStorefrontSettingsRequest::create(
        '/admin/storefront-settings',
        'PUT',
        ['hero_text_nb' => $value],
    );
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->validateResolved();

    (new StorefrontSettingsController)->update($request);
}

/** @param array<string, string|null> $expected */
function schemaRepairAssertOlderApplicationRead(int $settingsId, array $expected): void
{
    $oldApplication = (string) getenv('SCHEMA_REPAIR_OLD_APP_PATH');
    schemaRepairAssert(
        $oldApplication !== '' && is_file($oldApplication.'/bootstrap/app.php') && is_file($oldApplication.'/vendor/autoload.php'),
        'The retained old-application compatibility fixture is unavailable.',
        $oldApplication,
    );

    $code = <<<'PHP'
        require getcwd().'/vendor/autoload.php';
        $app = require getcwd().'/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $settings = App\Modules\Content\Models\StorefrontSettings::query()->findOrFail((int) getenv('SCHEMA_REPAIR_SETTINGS_ID'));
        echo json_encode([
            'hero_text_nb' => $settings->hero_text_nb,
            'hero_text_ru' => $settings->hero_text_ru,
            'phone_display' => $settings->phone_display,
            'map_url' => $settings->map_url,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        PHP;
    $process = new Process(
        [PHP_BINARY, '-r', $code],
        $oldApplication,
        ['SCHEMA_REPAIR_SETTINGS_ID' => (string) $settingsId],
        null,
        60,
    );
    $process->run();

    schemaRepairAssert($process->isSuccessful(), 'The retained older application could not read the additive/widened schema.', $process->getErrorOutput());
    $actual = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    schemaRepairAssert($actual === $expected, 'The retained older application read unexpected settings values.', $actual);
}

function schemaRepairScenarioContentAndOldApplicationCompatibility(): void
{
    $oldNb = 'Bestill hjemme, på jobb eller på verksted. EasyTire gjør sesongskiftet enkelt, med tydelig oppfølging før avtalen bekreftes.';
    $oldRu = 'Закажите выезд домой, на работу или приезжайте в мастерскую. EasyTire упростит сезонную замену и подтвердит детали до визита.';
    $appliedNb = 'Bestill dekkskift hjemme, på jobb eller på verksted. EasyTire gjør sesongskiftet enkelt, med tydelig oppfølging før avtalen bekreftes.';
    $operatorIntermediate = 'Operatørens mellomversjon.';
    $partialJournal = [
        'release' => 'pre-existing-operator-journal',
        'state' => 'applied',
        'changes' => [
            'business_title_nb' => [
                'original' => 'Tidligere bedriftsoverskrift',
                'applied' => 'Operatørens bedriftsoverskrift',
                'operator_owned' => true,
            ],
        ],
    ];

    schemaRepairFreshBaseline();
    schemaRepairApplyHistoricalStagingShape();
    schemaRepairReproduceLegacyFailure();
    schemaRepairAssertCurrentPartialState();
    $fixtures = schemaRepairInsertFixtures();
    schemaRepairAssert($fixtures['settings_id'] === 1, 'The admin-path fixture must use the singleton storefront settings row.', $fixtures['settings_id']);
    $now = '2026-09-13 00:00:00';

    DB::table('storefront_settings')->where('id', $fixtures['settings_id'])->update([
        'hero_text_nb' => $oldNb,
        'hero_text_ru' => $oldRu,
        'phone_display' => 'Operator-kept unrelated value',
    ]);
    $fixtures['settings']['phone_display'] = 'Operator-kept unrelated value';
    $journalSettingsId = (int) DB::table('storefront_settings')->insertGetId([
        'business_title_nb' => 'Operatørens bedriftsoverskrift',
        'customer_homepage_composition_manifest' => json_encode($partialJournal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    schemaRepairArtisan('db:seed', [
        '--class' => PublicServicePriceSeeder::class,
        '--force' => true,
    ]);
    $preMigrationCounts = schemaRepairTableCounts();

    schemaRepairRunAllMigrations();
    schemaRepairAssertFinalSchema();
    schemaRepairAssertFixtures($fixtures);
    schemaRepairAssert(schemaRepairTableCounts() === $preMigrationCounts, 'Schema recovery changed rows before the scoped content release.');

    $journalAfterMigration = (array) DB::table('storefront_settings')->find($journalSettingsId);
    schemaRepairAssert(
        schemaRepairCanonicalJson(json_decode((string) $journalAfterMigration['customer_homepage_composition_manifest'], true, flags: JSON_THROW_ON_ERROR))
            === schemaRepairCanonicalJson($partialJournal),
        'Pre-existing operator journal changed during schema recovery.',
        $journalAfterMigration['customer_homepage_composition_manifest'],
    );
    schemaRepairAssert($journalAfterMigration['business_title_nb'] === 'Operatørens bedriftsoverskrift', 'Pre-existing operator content changed during schema recovery.', $journalAfterMigration);

    schemaRepairArtisan('db:seed', [
        '--class' => CustomerHomepageReleaseSeeder::class,
        '--force' => true,
    ]);
    $afterFirstReleaseCounts = schemaRepairTableCounts();
    schemaRepairArtisan('db:seed', [
        '--class' => CustomerHomepageReleaseSeeder::class,
        '--force' => true,
    ]);
    schemaRepairAssert(schemaRepairTableCounts() === $afterFirstReleaseCounts, 'Repeating the scoped release seeder changed row counts.');

    schemaRepairApplyOperatorHeroEdit($operatorIntermediate);
    schemaRepairApplyOperatorHeroEdit($appliedNb);
    schemaRepairArtisan('db:seed', [
        '--class' => RollbackCustomerHomepageReleaseSeeder::class,
        '--force' => true,
    ]);
    schemaRepairArtisan('db:seed', [
        '--class' => RollbackCustomerHomepageReleaseSeeder::class,
        '--force' => true,
    ]);
    schemaRepairArtisan('db:seed', [
        '--class' => CustomerHomepageReleaseSeeder::class,
        '--force' => true,
    ]);

    $settings = (array) DB::table('storefront_settings')->find($fixtures['settings_id']);
    $manifest = json_decode((string) $settings['customer_homepage_composition_manifest'], true, flags: JSON_THROW_ON_ERROR);
    schemaRepairAssert($settings['hero_text_nb'] === $appliedNb, 'Guarded rollback lost the operator-owned Norwegian hero value.', $settings['hero_text_nb']);
    schemaRepairAssert($settings['hero_text_ru'] === $oldRu, 'Guarded rollback did not restore the untouched Russian hero value.', $settings['hero_text_ru']);
    schemaRepairAssert($settings['phone_display'] === 'Operator-kept unrelated value', 'Scoped release changed unrelated operator content.', $settings['phone_display']);
    schemaRepairAssert(($manifest['state'] ?? null) === 'rolled_back', 'Release was silently reapplied after guarded rollback.', $manifest);
    schemaRepairAssert(($manifest['changes']['hero_text_nb']['operator_owned'] ?? false) === true, 'Operator ownership was not retained in the release journal.', $manifest);

    $journalAfterRelease = (array) DB::table('storefront_settings')->find($journalSettingsId);
    schemaRepairAssert(
        schemaRepairCanonicalJson(json_decode((string) $journalAfterRelease['customer_homepage_composition_manifest'], true, flags: JSON_THROW_ON_ERROR))
            === schemaRepairCanonicalJson($partialJournal),
        'Unrelated pre-existing operator journal changed during release/rollback.',
        $journalAfterRelease['customer_homepage_composition_manifest'],
    );
    schemaRepairAssertFixtures($fixtures, ['updated_at']);

    schemaRepairAssertOlderApplicationRead((int) $fixtures['settings_id'], [
        'hero_text_nb' => $appliedNb,
        'hero_text_ru' => $oldRu,
        'phone_display' => 'Operator-kept unrelated value',
        'map_url' => str_repeat('a', 2048),
    ]);
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
    schemaRepairScenarioInterruptedUrlConversion();
    $generatedUrlConflict = schemaRepairScenarioGeneratedUrlFailsClosed();
    $crossSchemaForeignKeyConflict = schemaRepairScenarioForeignKeyConflictFailsClosed('cross-schema');
    $changedUpdateForeignKeyConflict = schemaRepairScenarioForeignKeyConflictFailsClosed('changed-update');
    $prefixUniqueConflict = schemaRepairScenarioUniqueIndexConflictFailsClosed('prefix');
    $expressionUniqueConflict = schemaRepairScenarioUniqueIndexConflictFailsClosed('expression');
    $compositeUniqueConflict = schemaRepairScenarioUniqueIndexConflictFailsClosed('composite');
    $invisibleUniqueConflict = schemaRepairScenarioUniqueIndexConflictFailsClosed('invisible');
    $nonBtreeUniqueConflict = schemaRepairScenarioUniqueIndexConflictFailsClosed('non-btree');
    $conflict = schemaRepairScenarioConflictFailsClosed();
    schemaRepairScenarioContentAndOldApplicationCompatibility();
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
            'interrupted_url_conversion_recovery' => true,
            'correct_text_is_noop' => true,
            'repeat_is_idempotent' => true,
            'later_migration_already_recorded' => true,
            'synthetic_data_preserved' => true,
            'generated_text_url_rejected' => str_contains($generatedUrlConflict, 'ordinary nullable DEFAULT NULL'),
            'cross_schema_foreign_key_rejected' => str_contains($crossSchemaForeignKeyConflict, 'current database'),
            'changed_on_update_foreign_key_rejected' => str_contains($changedUpdateForeignKeyConflict, 'ON UPDATE RESTRICT/NO ACTION'),
            'prefix_unique_index_rejected_unchanged' => $prefixUniqueConflict['before'] === $prefixUniqueConflict['after'],
            'expression_unique_index_rejected_unchanged' => $expressionUniqueConflict['before'] === $expressionUniqueConflict['after'],
            'composite_unique_index_rejected_unchanged' => $compositeUniqueConflict['before'] === $compositeUniqueConflict['after'],
            'invisible_unique_index_rejected_unchanged' => $invisibleUniqueConflict['before'] === $invisibleUniqueConflict['after'],
            'non_btree_unique_index_rejected_unchanged' => $nonBtreeUniqueConflict['before'] === $nonBtreeUniqueConflict['after'],
            'scoped_content_release_and_guarded_rollback' => true,
            'retained_application_read_compatibility' => true,
            'conflict_fails_closed' => str_contains($conflict, 'mobile_details_title_nb'),
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, "MySQL schema repair regression failed: {$exception->getMessage()}\n");
    exit(1);
}
