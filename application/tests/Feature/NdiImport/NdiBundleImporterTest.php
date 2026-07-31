<?php

use App\Modules\Catalog\Enums\CatalogState;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\SupplierSku;
use App\Modules\NdiImport\Exceptions\NdiPublishFailed;
use App\Modules\NdiImport\NdiBundleImporter;
use App\Modules\NdiImport\NdiCatalogPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    config()->set('ndi.raw_disk', 'local');
    config()->set('ndi.catalog_currency', 'NOK');
    config()->set('ndi.parser_version', 'test-1.0.0');
});

test('a complete valid NDI bundle is published transactionally with provenance', function (): void {
    $outcome = app(NdiBundleImporter::class)->import(validNdiBundle(), 'test', isRedacted: true);

    expect($outcome->status)->toBe('succeeded')
        ->and($outcome->published())->toBeTrue()
        ->and($outcome->createdCount)->toBeGreaterThan(0)
        ->and(DB::table('products')->count())->toBe(6)
        ->and(DB::table('supplier_skus')->count())->toBe(7)
        ->and(DB::table('sku_prices')->count())->toBe(12)
        ->and(DB::table('sku_price_history')->count())->toBe(12)
        ->and(DB::table('stock_snapshots')->count())->toBe(49)
        ->and(DB::table('current_stock')->count())->toBe(49)
        ->and(DB::table('source_artifacts')->count())->toBe(3)
        ->and(DB::table('import_run_artifacts')->where('import_run_id', $outcome->runId)->count())->toBe(3);

    $leadingZeroSku = SupplierSku::query()->where('external_sku', '0001234567890')->firstOrFail();
    $stockOnlySku = SupplierSku::query()->where('external_sku', 'TEST-STOCK-ONLY-001')->firstOrFail();

    expect($leadingZeroSku->external_sku)->toBe('0001234567890')
        ->and(strlen($leadingZeroSku->external_sku))->toBe(13)
        ->and($leadingZeroSku->product)->not->toBeNull()
        ->and($stockOnlySku->catalog_state)->toBe(CatalogState::StockOnly)
        ->and($stockOnlySku->product_id)->toBeNull();

    foreach (DB::table('source_artifacts')->pluck('storage_key') as $storageKey) {
        Storage::disk('local')->assertExists($storageKey);
    }
});

test('repeating the exact successful bundle and parser version is a no-op duplicate run', function (): void {
    $importer = app(NdiBundleImporter::class);
    $first = $importer->import(validNdiBundle(), 'test', isRedacted: true);
    $before = catalogStateCounts();
    $second = $importer->import(validNdiBundle(), 'test', isRedacted: true);

    expect($first->status)->toBe('succeeded')
        ->and($second->status)->toBe('duplicate')
        ->and(catalogStateCounts())->toBe($before)
        ->and(DB::table('source_artifacts')->count())->toBe(3)
        ->and(DB::table('import_runs')->count())->toBe(2)
        ->and(DB::table('import_run_artifacts')->count())->toBe(6);
});

test('an invalid decimal fails the new run and leaves the last published state unchanged', function (): void {
    $importer = app(NdiBundleImporter::class);
    $published = $importer->import(validNdiBundle(), 'test', isRedacted: true);
    $before = catalogStateCounts();

    $failed = $importer->import([
        ndiFixture('invalid_decimal.xlsx'),
        ndiFixture('wheels_valid.xlsx'),
        ndiFixture('stock_all_warehouses.xlsx'),
    ], 'test', isRedacted: true);

    $rejection = DB::table('import_rejections')->where('import_run_id', $failed->runId)->first();

    expect($published->status)->toBe('succeeded')
        ->and($failed->status)->toBe('failed')
        ->and($failed->rejectedCount)->toBe(1)
        ->and($rejection->row_number)->toBe(2)
        ->and($rejection->field_name)->toBe('Net_Price')
        ->and(catalogStateCounts())->toBe($before);
});

test('an incomplete bundle is quarantined before publication', function (): void {
    $outcome = app(NdiBundleImporter::class)->import([
        ndiFixture('tires_valid.xlsx'),
        ndiFixture('wheels_valid.xlsx'),
    ], 'test', isRedacted: true);

    expect($outcome->status)->toBe('failed')
        ->and($outcome->rejectedCount)->toBe(1)
        ->and(DB::table('products')->count())->toBe(0)
        ->and(DB::table('source_artifacts')->count())->toBe(2)
        ->and(DB::table('import_rejections')->where('import_run_id', $outcome->runId)->value('reason'))
        ->toBe('missing_dataset_roles:stock');
});

test('a publisher failure rolls back all domain changes and records a failed run', function (): void {
    $publisher = Mockery::mock(NdiCatalogPublisher::class);
    $publisher->shouldReceive('publish')->once()->andReturnUsing(function (): never {
        Brand::query()->create(['canonical_name' => 'TEST ROLLBACK', 'slug' => 'test-rollback']);

        throw new RuntimeException('Synthetic publish failure.');
    });
    app()->instance(NdiCatalogPublisher::class, $publisher);

    try {
        app(NdiBundleImporter::class)->import(validNdiBundle(), 'test', isRedacted: true);
        test()->fail('The synthetic publisher failure was not raised.');
    } catch (NdiPublishFailed $exception) {
        expect(Brand::query()->where('slug', 'test-rollback')->exists())->toBeFalse()
            ->and(DB::table('products')->count())->toBe(0)
            ->and(DB::table('import_runs')->where('id', $exception->runId)->value('status'))->toBe('failed');
    }
});

/** @return list<string> */
function validNdiBundle(): array
{
    return [
        ndiFixture('tires_valid.xlsx'),
        ndiFixture('wheels_valid.xlsx'),
        ndiFixture('stock_all_warehouses.xlsx'),
    ];
}

/** @return array<string, int> */
function catalogStateCounts(): array
{
    return [
        'products' => DB::table('products')->count(),
        'supplier_skus' => DB::table('supplier_skus')->count(),
        'prices' => DB::table('sku_prices')->count(),
        'price_history' => DB::table('sku_price_history')->count(),
        'stock_snapshots' => DB::table('stock_snapshots')->count(),
        'current_stock' => DB::table('current_stock')->count(),
        'entity_changes' => DB::table('entity_changes')->count(),
    ];
}
