<?php

use App\Modules\Catalog\Enums\TaxMode;
use App\Modules\NdiApi\NdiIntegrationProfile;
use App\Modules\NdiApi\NdiSandboxCatalogManager;
use App\Modules\Operations\SystemHealth;
use App\Modules\Pricing\Enums\PricingRuleScope;
use App\Modules\Pricing\Models\PricingRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set([
        'health.disk_free_warning_bytes' => 0,
        'health.ndi_success_max_age_minutes' => 60,
        'health.require_heartbeats' => false,
        'payments.enabled' => false,
        'ndi_api.sandbox.enabled' => false,
    ]);
});

test('a published live NDI catalog without a successful stock import is unhealthy', function (): void {
    $inbox = createHealthTestInbox();

    try {
        config()->set('ndi.inbox_path', $inbox);
        createPublishedHealthTestCatalog();

        $report = app(SystemHealth::class)->report();

        expect($report['ndi_live_catalog_published'])->toBeTrue()
            ->and($report['ndi_status'])->toBeNull()
            ->and($report['ndi_age_minutes'])->toBeNull()
            ->and($report['ndi_fresh'])->toBeFalse()
            ->and($report['ndi_healthy'])->toBeFalse()
            ->and(app(SystemHealth::class)->healthy())->toBeFalse();
    } finally {
        @rmdir($inbox);
    }
});

test('sandbox unrelated and non-stock imports cannot mask a missing live NDI stock import', function (): void {
    $this->freezeTime();
    $inbox = createHealthTestInbox();

    try {
        config()->set('ndi.inbox_path', $inbox);
        $liveSupplierId = createPublishedHealthTestCatalog();
        $sandboxSupplierId = createHealthTestSupplier('ndi-sandbox-health', NdiIntegrationProfile::SANDBOX, true);
        $otherSupplierId = createHealthTestSupplier('other-supplier-health');

        createHealthTestImport($sandboxSupplierId, 'stock', 2);
        createHealthTestImport($otherSupplierId, 'stock', 3);
        createHealthTestImport($liveSupplierId, 'catalog', 1);

        $report = app(SystemHealth::class)->report();

        expect($report['ndi_age_minutes'])->toBeNull()
            ->and($report['ndi_fresh'])->toBeFalse()
            ->and($report['ndi_healthy'])->toBeFalse()
            ->and(app(SystemHealth::class)->healthy())->toBeFalse();
    } finally {
        @rmdir($inbox);
    }
});

test('a fresh live NDI stock import recovers stale supplier health', function (): void {
    $this->freezeTime();
    $inbox = createHealthTestInbox();

    try {
        config()->set('ndi.inbox_path', $inbox);
        $supplierId = createPublishedHealthTestCatalog();
        $runId = createHealthTestImport($supplierId, 'stock', 61);

        $stale = app(SystemHealth::class)->report();
        expect($stale['ndi_age_minutes'])->toBe(61)
            ->and($stale['ndi_fresh'])->toBeFalse()
            ->and($stale['ndi_healthy'])->toBeFalse()
            ->and(app(SystemHealth::class)->healthy())->toBeFalse();

        DB::table('import_runs')->where('id', $runId)->update([
            'finished_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);
        DB::table('current_stock')->where('source_artifact_id', DB::table('import_run_artifacts')
            ->where('import_run_id', $runId)
            ->value('source_artifact_id'))
            ->update(['observed_at' => now()->subMinutes(5)]);

        $fresh = app(SystemHealth::class)->report();
        expect($fresh['ndi_age_minutes'])->toBe(5)
            ->and($fresh['ndi_fresh'])->toBeTrue()
            ->and($fresh['ndi_healthy'])->toBeTrue()
            ->and($fresh['ndi_inbox_configured'])->toBeTrue()
            ->and($fresh['ndi_inbox_directory'])->toBeTrue()
            ->and($fresh['ndi_inbox_readable'])->toBeTrue()
            ->and(app(SystemHealth::class)->healthy())->toBeTrue();
    } finally {
        @rmdir($inbox);
    }
});

test('supplier health cannot outlive the storefront stock purchase window', function (): void {
    $this->freezeTime();
    $inbox = createHealthTestInbox();

    try {
        config()->set([
            'ndi.inbox_path' => $inbox,
            'health.ndi_success_max_age_minutes' => 1440,
            'storefront.stock_max_age_minutes' => 180,
        ]);
        $supplierId = createPublishedHealthTestCatalog();
        createHealthTestImport($supplierId, 'stock', 181);

        $report = app(SystemHealth::class)->report();

        expect($report['ndi_age_minutes'])->toBe(181)
            ->and($report['ndi_fresh'])->toBeFalse()
            ->and($report['ndi_healthy'])->toBeFalse()
            ->and(app(SystemHealth::class)->healthy())->toBeFalse();
    } finally {
        @rmdir($inbox);
    }
});

test('a missing inbox configuration is explicit redacted and unhealthy for a live catalog', function (): void {
    $this->freezeTime();
    config()->set('ndi.inbox_path', null);
    $supplierId = createPublishedHealthTestCatalog();
    createHealthTestImport($supplierId, 'stock', 5);

    $report = app(SystemHealth::class)->report();

    expect($report['ndi_fresh'])->toBeTrue()
        ->and($report['ndi_inbox_configured'])->toBeFalse()
        ->and($report['ndi_inbox_directory'])->toBeFalse()
        ->and($report['ndi_inbox_readable'])->toBeFalse()
        ->and($report['ndi_inbox_ready'])->toBeFalse()
        ->and(array_key_exists('ndi_inbox_path', $report))->toBeFalse()
        ->and(app(SystemHealth::class)->healthy())->toBeFalse();
});

test('inbox receipt failures and the latest retryable age are exposed without file identity', function (): void {
    $this->freezeTime();
    insertHealthTestReceipt('failed', 20, 'a');
    insertHealthTestReceipt('retryable', 7, 'b');

    $report = app(SystemHealth::class)->report();

    expect($report['ndi_inbox_failed'])->toBe(1)
        ->and($report['ndi_inbox_retryable'])->toBe(1)
        ->and($report['ndi_inbox_latest_status'])->toBe('retryable')
        ->and($report['ndi_inbox_latest_age_minutes'])->toBe(7)
        ->and(array_key_exists('ndi_inbox_failure_code', $report))->toBeFalse()
        ->and(array_key_exists('ndi_inbox_file_key', $report))->toBeFalse();
});

test('historical inbox failures remain observable after a corrected successful receipt', function (): void {
    $this->freezeTime();
    insertHealthTestReceipt('failed', 20, 'older-failure');
    insertHealthTestReceipt('succeeded', 5, 'newer-success');

    $report = app(SystemHealth::class)->report();

    expect($report['ndi_inbox_latest_status'])->toBe('succeeded')
        ->and($report['ndi_inbox_failed'])->toBe(1)
        ->and($report['ndi_inbox_receipts_healthy'])->toBeTrue();
});

test('an unconfigured inbox is unhealthy even before live catalog publication', function (): void {
    config()->set('ndi.inbox_path', null);

    expect(app(SystemHealth::class)->report()['ndi_inbox_ready'])->toBeFalse()
        ->and(app(SystemHealth::class)->healthy())->toBeFalse();
});

test('payment health follows the same strict provider and environment contract as the gateway', function (): void {
    config()->set([
        'payments.enabled' => true,
        'payments.provider' => 'dintero',
        'payments.dintero.environment' => 'test',
        'payments.dintero.test_account_id' => 'T12345678',
        'payments.dintero.client_id' => 'client',
        'payments.dintero.client_secret' => 'secret',
        'payments.dintero.require_callback_signature' => true,
        'payments.dintero.callback_secret' => 'callback-secret',
    ]);

    expect(app(SystemHealth::class)->report()['payment_configured'])->toBeTrue();

    config()->set('payments.provider', ' dintero ');
    expect(app(SystemHealth::class)->report()['payment_configured'])->toBeFalse();

    config()->set([
        'payments.provider' => 'dintero',
        'payments.dintero.environment' => 'bogus',
    ]);
    $report = app(SystemHealth::class)->report();
    expect($report['payment_configured'])->toBeFalse()
        ->and($report['payment_environment'])->toBe('invalid');
});

test('Dintero test mode is unhealthy when the application runs in production', function (): void {
    $originalEnvironment = app()->environment();
    app()->detectEnvironment(fn (): string => 'production');

    try {
        config()->set([
            'payments.enabled' => true,
            'payments.provider' => 'dintero',
            'payments.dintero.environment' => 'test',
            'payments.dintero.test_account_id' => 'T12345678',
            'payments.dintero.client_id' => 'client',
            'payments.dintero.client_secret' => 'secret',
            'payments.dintero.require_callback_signature' => true,
            'payments.dintero.callback_secret' => 'callback-secret',
        ]);

        expect(app(SystemHealth::class)->report()['payment_configured'])->toBeFalse();
    } finally {
        app()->detectEnvironment(fn (): string => $originalEnvironment);
    }
});

test('sandbox readiness requires a published active fixture with fresh approved stock and a valid price', function (): void {
    $this->freezeTime();
    config()->set([
        'ndi_api.sandbox.enabled' => true,
        'ndi_api.sandbox.inquiry_enabled' => true,
        'ndi_api.sandbox.order_enabled' => true,
        'ndi_api.c1_test_mode' => true,
        'ndi_api.sandbox.quantity' => 2,
    ]);
    $sku = app(NdiSandboxCatalogManager::class)->refresh();

    $ready = app(SystemHealth::class)->report();
    expect($ready['ndi_sandbox_fixture_active'])->toBeTrue()
        ->and($ready['ndi_sandbox_fixture_stock_ready'])->toBeTrue()
        ->and($ready['ndi_sandbox_fixture_price_ready'])->toBeTrue()
        ->and($ready['ndi_sandbox_ready'])->toBeTrue();

    DB::table('current_stock')->where('supplier_sku_id', $sku->id)->update([
        'observed_at' => now()->subMinutes((int) config('storefront.stock_max_age_minutes') + 1),
    ]);

    expect(app(SystemHealth::class)->report()['ndi_sandbox_fixture_stock_ready'])->toBeFalse()
        ->and(app(SystemHealth::class)->report()['ndi_sandbox_ready'])->toBeFalse();

    DB::table('current_stock')->where('supplier_sku_id', $sku->id)->update(['observed_at' => now()]);
    DB::table('sku_prices')->where('supplier_sku_id', $sku->id)->update(['amount' => 0]);

    expect(app(SystemHealth::class)->report()['ndi_sandbox_fixture_price_ready'])->toBeFalse()
        ->and(app(SystemHealth::class)->report()['ndi_sandbox_ready'])->toBeFalse();
});

test('sandbox health validates the effective storefront price instead of any matching rule', function (): void {
    $this->freezeTime();
    config()->set([
        'ndi_api.sandbox.enabled' => true,
        'ndi_api.sandbox.inquiry_enabled' => true,
        'ndi_api.sandbox.order_enabled' => true,
        'ndi_api.c1_test_mode' => true,
        'ndi_api.sandbox.quantity' => 2,
        'ndi_api.sandbox.price_minor' => 1000,
    ]);
    app(NdiSandboxCatalogManager::class)->refresh();
    PricingRule::create([
        'name' => 'Conflicting higher priority sandbox price',
        'scope' => PricingRuleScope::Global,
        'scope_key' => null,
        'source_price_list_code' => NdiSandboxCatalogManager::PRICE_LIST_CODE,
        'source_tax_mode' => TaxMode::Gross,
        'currency' => 'NOK',
        'markup_basis_points' => 10000,
        'fixed_markup_minor' => 0,
        'rounding_increment_minor' => 1,
        'priority' => 20000,
        'is_active' => true,
    ]);

    $report = app(SystemHealth::class)->report();

    expect($report['ndi_sandbox_fixture_price_ready'])->toBeFalse()
        ->and($report['ndi_sandbox_ready'])->toBeFalse();
});

test('sandbox health follows authoritative national stock instead of any fresh secondary row', function (): void {
    $this->freezeTime();
    config()->set([
        'ndi_api.sandbox.enabled' => true,
        'ndi_api.sandbox.inquiry_enabled' => true,
        'ndi_api.sandbox.order_enabled' => true,
        'ndi_api.c1_test_mode' => true,
        'ndi_api.sandbox.quantity' => 2,
        'ndi_api.sandbox.price_minor' => 1000,
    ]);
    $sku = app(NdiSandboxCatalogManager::class)->refresh();
    $artifactId = DB::table('current_stock')->where('supplier_sku_id', $sku->id)->value('source_artifact_id');
    $warehouseId = DB::table('warehouses')->insertGetId([
        'supplier_id' => $sku->supplier_id,
        'code' => 'norway',
        'name' => 'National availability',
        'country_code' => 'NO',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('current_stock')->insert([
        'supplier_sku_id' => $sku->id,
        'warehouse_id' => $warehouseId,
        'quantity' => 0,
        'observed_at' => now()->subMinutes((int) config('storefront.stock_max_age_minutes') + 1),
        'source_artifact_id' => $artifactId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $report = app(SystemHealth::class)->report();

    expect($report['ndi_sandbox_fixture_active'])->toBeTrue()
        ->and($report['ndi_sandbox_fixture_stock_ready'])->toBeFalse()
        ->and($report['ndi_sandbox_ready'])->toBeFalse();
});

function createHealthTestInbox(): string
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'easytire-health-inbox-'.bin2hex(random_bytes(5));
    mkdir($path, 0700, true);

    return $path;
}

function createHealthTestSupplier(
    string $code,
    string $integrationProfile = NdiIntegrationProfile::LIVE,
    bool $isTestFixture = false,
): int {
    return DB::table('suppliers')->insertGetId([
        'code' => $code,
        'name' => 'Health test '.$code,
        'integration_profile' => $integrationProfile,
        'is_test_fixture' => $isTestFixture,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function createPublishedHealthTestCatalog(): int
{
    $supplierId = createHealthTestSupplier('ndi');
    $brandId = DB::table('brands')->insertGetId([
        'canonical_name' => 'Health Test',
        'slug' => 'health-test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $productId = DB::table('products')->insertGetId([
        'product_type' => 'tire',
        'brand_id' => $brandId,
        'model_name' => 'Live NDI',
        'title' => 'Live NDI health test',
        'publication_status' => 'published',
        'first_seen_at' => now(),
        'last_catalog_seen_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('supplier_skus')->insert([
        'supplier_id' => $supplierId,
        'product_id' => $productId,
        'external_sku' => 'HEALTH-LIVE-001',
        'catalog_state' => 'catalog',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $supplierId;
}

function createHealthTestImport(int $supplierId, string $datasetRole, int $ageMinutes): string
{
    $artifactId = (string) Str::ulid();
    DB::table('source_artifacts')->insert([
        'id' => $artifactId,
        'supplier_id' => $supplierId,
        'kind' => $datasetRole,
        'original_filename' => $datasetRole.'.xlsx',
        'storage_key' => 'health-tests/'.$artifactId.'.xlsx',
        'byte_size' => 1,
        'sha256' => hash('sha256', $artifactId),
        'received_at' => now()->subMinutes($ageMinutes),
        'is_redacted' => true,
    ]);
    $runId = (string) Str::ulid();
    DB::table('import_runs')->insert([
        'id' => $runId,
        'supplier_id' => $supplierId,
        'run_type' => 'partial_feed',
        'status' => 'succeeded',
        'trigger' => 'health-test',
        'parser_version' => 'health-test',
        'started_at' => now()->subMinutes($ageMinutes + 1),
        'finished_at' => now()->subMinutes($ageMinutes),
        'created_at' => now()->subMinutes($ageMinutes + 1),
        'updated_at' => now()->subMinutes($ageMinutes),
    ]);
    DB::table('import_run_artifacts')->insert([
        'import_run_id' => $runId,
        'source_artifact_id' => $artifactId,
        'dataset_role' => $datasetRole,
        'parser_version' => 'health-test',
    ]);

    if ($datasetRole === 'stock') {
        $skuId = DB::table('supplier_skus')->where('supplier_id', $supplierId)->value('id');
        if ($skuId !== null) {
            $warehouseId = DB::table('warehouses')->insertGetId([
                'supplier_id' => $supplierId,
                'code' => 'norway',
                'name' => 'Norway',
                'country_code' => 'NO',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('current_stock')->insert([
                'supplier_sku_id' => $skuId,
                'warehouse_id' => $warehouseId,
                'quantity' => 4,
                'observed_at' => now()->subMinutes($ageMinutes),
                'source_artifact_id' => $artifactId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    return $runId;
}

function insertHealthTestReceipt(string $status, int $ageMinutes, string $seed): void
{
    DB::table('ndi_inbox_receipts')->insert([
        'fingerprint' => hash('sha256', 'fingerprint-'.$seed),
        'file_key_hash' => hash('sha256', 'file-'.$seed),
        'sha256' => hash('sha256', 'contents-'.$seed),
        'byte_size' => 100,
        'file_modified_at' => 1,
        'file_changed_at' => 1,
        'file_inode' => ord($seed),
        'status' => $status,
        'created_at' => now()->subMinutes($ageMinutes),
        'updated_at' => now()->subMinutes($ageMinutes),
    ]);
}
