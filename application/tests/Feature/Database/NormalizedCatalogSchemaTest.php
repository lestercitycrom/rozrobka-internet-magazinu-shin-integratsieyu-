<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('the normalized catalog schema exposes the required tables and columns', function (): void {
    foreach ([
        'brands',
        'products',
        'supplier_skus',
        'tire_specs',
        'tire_labels',
        'wheel_specs',
        'price_lists',
        'sku_prices',
        'sku_price_history',
        'warehouses',
        'stock_snapshots',
        'current_stock',
        'entity_changes',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    expect(Schema::hasColumns('products', [
        'id', 'product_type', 'brand_id', 'model_name', 'title', 'publication_status',
        'first_seen_at', 'last_catalog_seen_at', 'created_at', 'updated_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('supplier_skus', [
            'id', 'supplier_id', 'product_id', 'external_sku', 'ean', 'supplier_item_id',
            'source_description', 'catalog_state', 'created_at', 'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('sku_price_history', [
            'id', 'supplier_sku_id', 'price_list_id', 'amount', 'observed_at',
            'source_artifact_id', 'created_at', 'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('current_stock', [
            'id', 'supplier_sku_id', 'warehouse_id', 'quantity', 'observed_at',
            'source_artifact_id', 'created_at', 'updated_at',
        ]))->toBeTrue();
});

test('supplier SKU is a unique string per supplier and can be stock only', function (): void {
    $supplierId = grayCreateSupplier();
    $sku = '0001234567890';

    DB::table('supplier_skus')->insert([
        'supplier_id' => $supplierId,
        'product_id' => null,
        'external_sku' => $sku,
        'catalog_state' => 'stock_only',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('supplier_skus')->value('external_sku'))->toBe($sku)
        ->and(DB::table('supplier_skus')->value('product_id'))->toBeNull()
        ->and(fn () => DB::table('supplier_skus')->insert([
            'supplier_id' => $supplierId,
            'external_sku' => $sku,
            'catalog_state' => 'catalog',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
});

test('current prices are unique while history is deduplicated by artifact', function (): void {
    $supplierId = grayCreateSupplier();
    $skuId = grayCreateSupplierSku($supplierId);
    $priceListId = grayCreatePriceList($supplierId);
    $firstArtifactId = grayCreateArtifact($supplierId, str_repeat('a', 64), 'raw/test-price-a.xlsx');
    $secondArtifactId = grayCreateArtifact($supplierId, str_repeat('b', 64), 'raw/test-price-b.xlsx');

    $current = [
        'supplier_sku_id' => $skuId,
        'price_list_id' => $priceListId,
        'amount' => '100.2500',
        'observed_at' => now(),
        'source_artifact_id' => $firstArtifactId,
        'created_at' => now(),
        'updated_at' => now(),
    ];

    DB::table('sku_prices')->insert($current);

    expect(fn () => DB::table('sku_prices')->insert([
        ...$current,
        'amount' => '101.0000',
        'source_artifact_id' => $secondArtifactId,
    ]))->toThrow(QueryException::class);

    DB::table('sku_price_history')->insert($current);

    expect(fn () => DB::table('sku_price_history')->insert($current))
        ->toThrow(QueryException::class);

    DB::table('sku_price_history')->insert([
        ...$current,
        'amount' => '101.0000',
        'source_artifact_id' => $secondArtifactId,
    ]);

    expect(DB::table('sku_price_history')->count())->toBe(2);
});

test('current and historical stock enforce their business keys', function (): void {
    $supplierId = grayCreateSupplier();
    $skuId = grayCreateSupplierSku($supplierId);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'supplier_id' => $supplierId,
        'code' => 'vestby',
        'name' => 'TEST-Vestby',
        'country_code' => 'NO',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $artifactId = grayCreateArtifact($supplierId, str_repeat('c', 64), 'raw/test-stock.xlsx');
    $runId = grayCreateRun($supplierId);

    $current = [
        'supplier_sku_id' => $skuId,
        'warehouse_id' => $warehouseId,
        'quantity' => 5,
        'observed_at' => now(),
        'source_artifact_id' => $artifactId,
        'created_at' => now(),
        'updated_at' => now(),
    ];

    DB::table('current_stock')->insert($current);

    expect(fn () => DB::table('current_stock')->insert([...$current, 'quantity' => 6]))
        ->toThrow(QueryException::class);

    $snapshot = [...$current, 'import_run_id' => $runId];
    DB::table('stock_snapshots')->insert($snapshot);

    expect(fn () => DB::table('stock_snapshots')->insert([...$snapshot, 'quantity' => 7]))
        ->toThrow(QueryException::class);
});

function grayCreateSupplier(): int
{
    return DB::table('suppliers')->insertGetId([
        'code' => 'test-'.Str::lower(Str::random(12)),
        'name' => 'TEST Supplier',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function grayCreateSupplierSku(int $supplierId): int
{
    return DB::table('supplier_skus')->insertGetId([
        'supplier_id' => $supplierId,
        'external_sku' => 'TEST-SKU-'.Str::upper(Str::random(12)),
        'catalog_state' => 'stock_only',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function grayCreatePriceList(int $supplierId): int
{
    return DB::table('price_lists')->insertGetId([
        'supplier_id' => $supplierId,
        'code' => 'test-net',
        'currency' => 'NOK',
        'tax_mode' => 'net',
        'valid_from' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function grayCreateArtifact(int $supplierId, string $hash, string $storageKey): string
{
    $id = (string) Str::ulid();

    DB::table('source_artifacts')->insert([
        'id' => $id,
        'supplier_id' => $supplierId,
        'kind' => 'xlsx',
        'original_filename' => 'test.xlsx',
        'storage_key' => $storageKey,
        'byte_size' => 1024,
        'sha256' => $hash,
        'received_at' => now(),
        'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'is_redacted' => true,
    ]);

    return $id;
}

function grayCreateRun(int $supplierId): string
{
    $id = (string) Str::ulid();

    DB::table('import_runs')->insert([
        'id' => $id,
        'supplier_id' => $supplierId,
        'run_type' => 'full',
        'status' => 'running',
        'trigger' => 'test',
        'parser_version' => 'test-1',
        'started_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}
