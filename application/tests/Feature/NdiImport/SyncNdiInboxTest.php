<?php

use App\Modules\Catalog\Enums\PublicationStatus;
use App\Modules\Catalog\Models\SupplierSku;
use App\Modules\Catalog\ReadModel\CatalogReadService;
use App\Modules\NdiImport\NdiBundleImporter;
use App\Modules\Operations\SystemHealth;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('stable inbox files import once while an unchanged artifact is safely skipped', function (): void {
    Storage::fake('local');
    config()->set('ndi.raw_disk', 'local');
    config()->set('ndi.catalog_currency', 'NOK');
    config()->set('ndi.parser_version', 'inbox-test-1.0');
    config()->set('ndi.inbox_stable_seconds', 30);
    $inbox = sys_get_temp_dir().DIRECTORY_SEPARATOR.'easytire-ndi-inbox-'.bin2hex(random_bytes(5));
    mkdir($inbox, 0700, true);
    $file = $inbox.DIRECTORY_SEPARATOR.'stock.xlsx';
    copy(ndiFixture('stock_all_warehouses.xlsx'), $file);
    touch($file, time() - 60);
    config()->set('ndi.inbox_path', $inbox);

    try {
        $this->artisan('ndi:inbox:sync')
            ->expectsOutputToContain('1 imported, 0 unchanged, 0 failed')
            ->assertSuccessful();
        expect(DB::table('import_runs')->count())->toBe(1)
            ->and(DB::table('import_runs')->value('run_type'))->toBe('partial_feed')
            ->and(DB::table('current_stock')->count())->toBeGreaterThan(0)
            ->and(CarbonImmutable::parse(DB::table('current_stock')->max('observed_at'))->timestamp)->toBe(filemtime($file));

        $this->artisan('ndi:inbox:sync')
            ->expectsOutputToContain('0 imported, 1 unchanged, 0 failed')
            ->assertSuccessful();
        expect(DB::table('import_runs')->count())->toBe(1);

        $firstObservedAt = DB::table('current_stock')->max('observed_at');
        $firstRunId = DB::table('import_runs')->value('id');
        $previousMtime = filemtime($file);
        $this->travel(4)->hours();
        config()->set('ndi.parser_version', 'inbox-test-2.0');
        touch($file, $previousMtime + 1);

        $this->artisan('ndi:inbox:sync')
            ->expectsOutputToContain('0 imported, 1 unchanged, 0 failed')
            ->assertSuccessful();
        expect(DB::table('import_runs')->count())->toBe(1)
            ->and(DB::table('ndi_inbox_receipts')->count())->toBe(2)
            ->and(DB::table('ndi_inbox_receipts')->distinct()->pluck('import_run_id')->all())->toBe([$firstRunId])
            ->and(DB::table('current_stock')->max('observed_at'))->toBe($firstObservedAt);
    } finally {
        @unlink($file);
        @rmdir($inbox);
    }
});

test('historical inbox delivery stays stale until a fresh new-content artifact arrives', function (): void {
    $this->freezeTime();
    Storage::fake('local');
    config()->set([
        'ndi.raw_disk' => 'local',
        'ndi.catalog_currency' => 'NOK',
        'ndi.parser_version' => 'inbox-delivery-time-1.0',
        'ndi.inbox_stable_seconds' => 30,
        'storefront.stock_max_age_minutes' => 180,
        'health.ndi_success_max_age_minutes' => 180,
        'health.require_heartbeats' => false,
        'payments.enabled' => false,
        'ndi_api.sandbox.enabled' => false,
    ]);
    $inbox = sys_get_temp_dir().DIRECTORY_SEPARATOR.'easytire-ndi-inbox-'.bin2hex(random_bytes(5));
    mkdir($inbox, 0700, true);
    $historical = $inbox.DIRECTORY_SEPARATOR.'stock-historical.xlsx';
    copy(ndiFixture('stock_all_warehouses.xlsx'), $historical);
    $historicalTimestamp = now()->subHours(4)->timestamp;
    touch($historical, $historicalTimestamp);
    config()->set('ndi.inbox_path', $inbox);

    try {
        app(NdiBundleImporter::class)->importAvailable([ndiFixture('tires_valid.xlsx')], 'test-catalog');
        DB::table('products')->update(['publication_status' => PublicationStatus::Published->value]);

        $this->artisan('ndi:inbox:sync')->assertSuccessful();

        $sku = SupplierSku::query()->where('external_sku', '0001234567890')->firstOrFail();
        $staleItem = app(CatalogReadService::class)->findPublicSku($sku->external_sku);
        $staleHealth = app(SystemHealth::class)->report();

        expect(CarbonImmutable::parse(DB::table('current_stock')->max('observed_at'))->timestamp)->toBe($historicalTimestamp)
            ->and($staleItem)->not->toBeNull()
            ->and($staleItem->stock->canPurchase(1))->toBeFalse()
            ->and($staleHealth['ndi_fresh'])->toBeFalse()
            ->and($staleHealth['ndi_healthy'])->toBeFalse();

        $fresh = $inbox.DIRECTORY_SEPARATOR.'stock-fresh.xlsx';
        copy(ndiFixture('stock_all_warehouses.xlsx'), $fresh);
        $zip = new ZipArchive;
        expect($zip->open($fresh))->toBeTrue();
        $zip->setArchiveComment('new-authoritative-delivery');
        $zip->close();
        $freshTimestamp = now()->subMinute()->timestamp;
        touch($fresh, $freshTimestamp);

        $this->artisan('ndi:inbox:sync')->assertSuccessful();

        $freshItem = app(CatalogReadService::class)->findPublicSku($sku->external_sku);
        $freshHealth = app(SystemHealth::class)->report();
        expect(CarbonImmutable::parse(DB::table('current_stock')->max('observed_at'))->timestamp)->toBe($freshTimestamp)
            ->and($freshItem)->not->toBeNull()
            ->and($freshItem->stock->canPurchase(1))->toBeTrue()
            ->and($freshHealth['ndi_fresh'])->toBeTrue()
            ->and($freshHealth['ndi_healthy'])->toBeTrue();
    } finally {
        @unlink($historical);
        @unlink($fresh ?? '');
        @rmdir($inbox);
    }
});

test('a previously failed artifact keeps its original receipt time when reparsed', function (): void {
    $this->freezeTime();
    Storage::fake('local');
    config()->set([
        'ndi.raw_disk' => 'local',
        'ndi.catalog_currency' => 'NOK',
        'ndi.parser_version' => 'inbox-reparse-1.0',
        'ndi.inbox_stable_seconds' => 30,
        'ndi.stock_drop_guard_min_baseline' => 8,
        'ndi.stock_minimum_previous_ratio' => 0.9,
        'storefront.stock_max_age_minutes' => 180,
        'health.ndi_success_max_age_minutes' => 180,
        'health.require_heartbeats' => false,
        'payments.enabled' => false,
        'ndi_api.sandbox.enabled' => false,
    ]);
    $inbox = sys_get_temp_dir().DIRECTORY_SEPARATOR.'easytire-ndi-inbox-'.bin2hex(random_bytes(5));
    mkdir($inbox, 0700, true);
    $file = $inbox.DIRECTORY_SEPARATOR.'stock-retry.xlsx';
    copy(ndiFixture('stock_all_warehouses.xlsx'), $file);
    $originalReceiptAt = now()->subHours(4);
    touch($file, $originalReceiptAt->timestamp);
    config()->set('ndi.inbox_path', $inbox);

    $supplierId = DB::table('suppliers')->insertGetId([
        'code' => 'ndi',
        'name' => 'NDI',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $baselineArtifactId = (string) str()->ulid();
    DB::table('source_artifacts')->insert([
        'id' => $baselineArtifactId,
        'supplier_id' => $supplierId,
        'kind' => 'fixture',
        'original_filename' => 'baseline.xlsx',
        'storage_key' => 'fixtures/'.$baselineArtifactId.'.xlsx',
        'byte_size' => 1,
        'sha256' => hash('sha256', $baselineArtifactId),
        'received_at' => now()->subHours(5),
        'is_redacted' => true,
    ]);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'supplier_id' => $supplierId,
        'code' => 'norway',
        'name' => 'Norway',
        'country_code' => 'NO',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    foreach (range(1, 8) as $index) {
        $skuId = DB::table('supplier_skus')->insertGetId([
            'supplier_id' => $supplierId,
            'product_id' => null,
            'external_sku' => 'BASELINE-'.$index,
            'catalog_state' => 'stock_only',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('current_stock')->insert([
            'supplier_sku_id' => $skuId,
            'warehouse_id' => $warehouseId,
            'quantity' => 1,
            'observed_at' => now()->subHours(5),
            'source_artifact_id' => $baselineArtifactId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    try {
        $this->artisan('ndi:inbox:sync')->assertFailed();
        $storedArtifact = DB::table('source_artifacts')->where('sha256', hash_file('sha256', $file))->first();
        expect($storedArtifact)->not->toBeNull()
            ->and(CarbonImmutable::parse($storedArtifact->received_at)->timestamp)->toBe($originalReceiptAt->timestamp)
            ->and(DB::table('import_runs')->where('status', 'failed')->count())->toBe(1);

        DB::table('current_stock')->where('source_artifact_id', $baselineArtifactId)->delete();
        DB::table('supplier_skus')->where('external_sku', 'like', 'BASELINE-%')->delete();
        config()->set('ndi.parser_version', 'inbox-reparse-2.0');
        touch($file, now()->subMinute()->timestamp);

        $this->artisan('ndi:inbox:sync')->assertSuccessful();

        expect(CarbonImmutable::parse(DB::table('current_stock')->max('observed_at'))->timestamp)
            ->toBe($originalReceiptAt->timestamp)
            ->and(app(SystemHealth::class)->report()['ndi_fresh'])->toBeFalse()
            ->and(DB::table('import_runs')->where('status', 'succeeded')->count())->toBe(1);
    } finally {
        @unlink($file);
        @rmdir($inbox);
    }
});

test('future observation is rejected before any import state is written', function (): void {
    Storage::fake('local');
    config()->set('ndi.raw_disk', 'local');

    expect(fn () => app(NdiBundleImporter::class)->importAvailable(
        [ndiFixture('stock_all_warehouses.xlsx')],
        observedAt: CarbonImmutable::now()->addMinutes(6),
    ))->toThrow(InvalidArgumentException::class, 'too far in the future');

    expect(DB::table('source_artifacts')->count())->toBe(0)
        ->and(DB::table('import_runs')->count())->toBe(0)
        ->and(DB::table('current_stock')->count())->toBe(0);
});

test('an older new-hash delivery cannot replace newer current stock', function (): void {
    Storage::fake('local');
    config()->set([
        'ndi.raw_disk' => 'local',
        'ndi.catalog_currency' => 'NOK',
        'ndi.parser_version' => 'inbox-monotonic-1.0',
    ]);
    $fresh = sys_get_temp_dir().DIRECTORY_SEPARATOR.'easytire-stock-fresh-'.bin2hex(random_bytes(5)).'.xlsx';
    $older = sys_get_temp_dir().DIRECTORY_SEPARATOR.'easytire-stock-older-'.bin2hex(random_bytes(5)).'.xlsx';
    copy(ndiFixture('stock_all_warehouses.xlsx'), $fresh);
    copy(ndiFixture('stock_all_warehouses.xlsx'), $older);
    $zip = new ZipArchive;
    expect($zip->open($older))->toBeTrue();
    $zip->setArchiveComment('older-distinct-content-hash');
    $zip->close();

    try {
        $importer = app(NdiBundleImporter::class);
        $freshAt = CarbonImmutable::now()->subMinute();
        $importer->importAvailable([$fresh], 'test-fresh', observedAt: $freshAt);
        $freshObservation = DB::table('current_stock')->max('observed_at');

        $importer->importAvailable([$older], 'test-older', observedAt: $freshAt->subHours(4));

        expect(DB::table('current_stock')->max('observed_at'))->toBe($freshObservation)
            ->and(DB::table('current_stock')->min('observed_at'))->toBe($freshObservation);
    } finally {
        @unlink($fresh);
        @unlink($older);
    }
});

test('missing or unconfigured inbox is an explicit failure', function (): void {
    config()->set('ndi.inbox_path', null);
    $this->artisan('ndi:inbox:sync')
        ->expectsOutputToContain('NDI inbox is not configured')
        ->assertFailed();

    config()->set('ndi.inbox_path', sys_get_temp_dir().DIRECTORY_SEPARATOR.'missing-easytire-inbox');
    $this->artisan('ndi:inbox:sync')->assertFailed();
});

test('inbox ignores symbolic links instead of importing files outside its root', function (): void {
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('Creating symlinks requires an optional Windows privilege.');
    }

    Storage::fake('local');
    config()->set('ndi.raw_disk', 'local');
    config()->set('ndi.inbox_stable_seconds', 30);
    $inbox = sys_get_temp_dir().DIRECTORY_SEPARATOR.'easytire-ndi-inbox-'.bin2hex(random_bytes(5));
    mkdir($inbox, 0700, true);
    $link = $inbox.DIRECTORY_SEPARATOR.'outside.xlsx';
    symlink(ndiFixture('stock_all_warehouses.xlsx'), $link);
    config()->set('ndi.inbox_path', $inbox);

    try {
        $this->artisan('ndi:inbox:sync')
            ->expectsOutputToContain('0 imported, 0 unchanged, 0 failed')
            ->assertSuccessful();
        expect(DB::table('import_runs')->count())->toBe(0);
    } finally {
        @unlink($link);
        @rmdir($inbox);
    }
});

test('a transient importer lock is retried after backoff instead of losing the receipt', function (): void {
    Storage::fake('local');
    config()->set('ndi.raw_disk', 'local');
    config()->set('ndi.inbox_stable_seconds', 30);
    config()->set('ndi.inbox_retry_backoff_minutes', 10);
    $inbox = sys_get_temp_dir().DIRECTORY_SEPARATOR.'easytire-ndi-inbox-'.bin2hex(random_bytes(5));
    mkdir($inbox, 0700, true);
    $file = $inbox.DIRECTORY_SEPARATOR.'stock.xlsx';
    copy(ndiFixture('stock_all_warehouses.xlsx'), $file);
    touch($file, time() - 60);
    config()->set('ndi.inbox_path', $inbox);
    $lock = Cache::lock('ndi-import:'.config('ndi.supplier_code'), 300);

    try {
        expect($lock->get())->toBeTrue();
        $this->artisan('ndi:inbox:sync')->assertFailed();
        expect(DB::table('ndi_inbox_receipts')->value('status'))->toBe('retryable');
        $lock->release();

        $this->travel(11)->minutes();
        $this->artisan('ndi:inbox:sync')->assertSuccessful();
        expect(DB::table('ndi_inbox_receipts')->value('status'))->toBe('succeeded')
            ->and(DB::table('current_stock')->count())->toBeGreaterThan(0);
    } finally {
        $lock->release();
        @unlink($file);
        @rmdir($inbox);
    }
});

test('a terminal invalid file cannot starve a later valid stock snapshot', function (): void {
    Storage::fake('local');
    config()->set('ndi.raw_disk', 'local');
    config()->set('ndi.catalog_currency', 'NOK');
    config()->set('ndi.inbox_stable_seconds', 30);
    $inbox = sys_get_temp_dir().DIRECTORY_SEPARATOR.'easytire-ndi-inbox-'.bin2hex(random_bytes(5));
    mkdir($inbox, 0700, true);
    $invalid = $inbox.DIRECTORY_SEPARATOR.'01-invalid.xlsx';
    $valid = $inbox.DIRECTORY_SEPARATOR.'02-stock.xlsx';
    copy(ndiFixture('invalid_decimal.xlsx'), $invalid);
    copy(ndiFixture('stock_all_warehouses.xlsx'), $valid);
    touch($invalid, time() - 120);
    touch($valid, time() - 60);
    config()->set('ndi.inbox_path', $inbox);

    try {
        $this->artisan('ndi:inbox:sync --limit=1')->assertFailed();
        expect(DB::table('ndi_inbox_receipts')->where('status', 'failed')->count())->toBe(1);

        $this->artisan('ndi:inbox:sync --limit=1')
            ->expectsOutputToContain('1 imported, 1 unchanged, 0 failed')
            ->assertSuccessful();
        expect(DB::table('current_stock')->count())->toBeGreaterThan(0);
    } finally {
        @unlink($invalid);
        @unlink($valid);
        @rmdir($inbox);
    }
});
