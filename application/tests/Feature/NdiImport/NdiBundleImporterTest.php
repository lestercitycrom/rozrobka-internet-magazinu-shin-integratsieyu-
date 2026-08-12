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
        ->and(DB::table('price_lists')->pluck('valid_from')->unique()->values()->all())
        ->toBe(['2000-01-01 00:00:00'])
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

test('validated catalog and stock files can publish independently for their real delivery cadence', function (): void {
    $importer = app(NdiBundleImporter::class);
    $catalog = $importer->importAvailable([ndiFixture('tires_valid.xlsx')], 'scheduled-inbox', isRedacted: true);

    expect($catalog->status)->toBe('succeeded')
        ->and(DB::table('products')->count())->toBeGreaterThan(0)
        ->and(DB::table('sku_prices')->count())->toBeGreaterThan(0)
        ->and(DB::table('current_stock')->count())->toBe(0)
        ->and(DB::table('import_runs')->where('id', $catalog->runId)->value('run_type'))->toBe('partial_feed');

    $stock = $importer->importAvailable([ndiFixture('stock_all_warehouses.xlsx')], 'scheduled-inbox', isRedacted: true);
    expect($stock->status)->toBe('succeeded')
        ->and(DB::table('current_stock')->count())->toBeGreaterThan(0)
        ->and(DB::table('import_run_artifacts')->where('import_run_id', $stock->runId)->value('dataset_role'))->toBe('stock');
});

test('empty and sharply truncated stock snapshots are quarantined without zeroing the current catalog', function (): void {
    config()->set('ndi.stock_drop_guard_min_baseline', 1);
    config()->set('ndi.stock_minimum_previous_ratio', 0.9);
    $importer = app(NdiBundleImporter::class);
    $initial = $importer->importAvailable([ndiFixture('stock_all_warehouses.xlsx')], 'scheduled-inbox', isRedacted: true);
    $before = DB::table('current_stock')
        ->orderBy('supplier_sku_id')->orderBy('warehouse_id')
        ->get(['supplier_sku_id', 'warehouse_id', 'quantity', 'observed_at'])
        ->map(fn (object $row): array => (array) $row)
        ->all();
    $empty = ndiStockFixtureWithRows(0);
    $truncated = ndiStockFixtureWithRows(3);

    try {
        $emptyOutcome = $importer->importAvailable([$empty], 'scheduled-inbox', isRedacted: true);
        $truncatedOutcome = $importer->importAvailable([$truncated], 'scheduled-inbox', isRedacted: true);

        expect($initial->status)->toBe('succeeded')
            ->and($emptyOutcome->status)->toBe('failed')
            ->and(DB::table('import_rejections')->where('import_run_id', $emptyOutcome->runId)->pluck('reason'))
            ->toContain('dataset_contains_no_rows')
            ->and($truncatedOutcome->status)->toBe('failed')
            ->and(DB::table('import_rejections')->where('import_run_id', $truncatedOutcome->runId)->pluck('reason'))
            ->toContain('stock_row_count_drop_requires_manual_override')
            ->and(DB::table('current_stock')
                ->orderBy('supplier_sku_id')->orderBy('warehouse_id')
                ->get(['supplier_sku_id', 'warehouse_id', 'quantity', 'observed_at'])
                ->map(fn (object $row): array => (array) $row)
                ->all())->toBe($before);

        $approved = $importer->importAvailable(
            [$truncated],
            'manual-approved-stock-drop',
            isRedacted: true,
            allowStockDrop: true,
        );
        expect($approved->status)->toBe('succeeded');
    } finally {
        @unlink($empty);
        @unlink($truncated);
    }
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

function ndiStockFixtureWithRows(int $dataRows): string
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ndi-stock-'.bin2hex(random_bytes(6)).'.xlsx';
    if (! copy(ndiFixture('stock_all_warehouses.xlsx'), $path)) {
        throw new RuntimeException('Unable to create the temporary stock workbook.');
    }

    $zip = new ZipArchive;
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Unable to open the temporary stock workbook.');
    }

    $entry = 'xl/worksheets/sheet1.xml';
    $xml = $zip->getFromName($entry);
    if (! is_string($xml)) {
        $zip->close();
        throw new RuntimeException('Unable to read the stock worksheet.');
    }

    $lastKeptRow = $dataRows + 1;
    $xml = preg_replace_callback(
        '/<(?:[A-Za-z0-9_]+:)?row\b[^>]*\br="(\d+)"[^>]*>.*?<\/(?:[A-Za-z0-9_]+:)?row>/s',
        fn (array $match): string => (int) $match[1] <= $lastKeptRow ? $match[0] : '',
        $xml,
    );
    if (! is_string($xml) || ! $zip->addFromString($entry, $xml)) {
        $zip->close();
        throw new RuntimeException('Unable to truncate the stock worksheet.');
    }
    $zip->close();

    return $path;
}
