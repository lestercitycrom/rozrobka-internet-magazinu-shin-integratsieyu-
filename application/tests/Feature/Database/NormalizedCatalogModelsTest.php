<?php

use App\Modules\Catalog\Enums\CatalogState;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Enums\PublicationStatus;
use App\Modules\Catalog\Enums\TaxMode;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\CurrentStock;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\SkuPrice;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\SupplierSku;
use App\Modules\Catalog\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('catalog models expose normalized tire relations and casts', function (): void {
    $supplier = Supplier::create([
        'code' => 'test-ndi',
        'name' => 'TEST NDI',
        'is_active' => true,
    ]);
    $brand = Brand::create([
        'canonical_name' => 'TEST Brand',
        'slug' => 'test-brand',
    ]);
    $product = Product::create([
        'product_type' => ProductType::Tire,
        'brand_id' => $brand->id,
        'model_name' => 'TEST Pattern',
        'title' => 'TEST Tire',
        'publication_status' => PublicationStatus::Draft,
        'first_seen_at' => now(),
    ]);
    $tireSpec = $product->tireSpec()->create([
        'width_mm' => '205.00',
        'aspect_ratio' => '55.00',
        'rim_diameter_in' => '16.00',
        'load_index' => '91',
        'speed_symbol' => 'V',
        'construction' => 'Radial tyre',
        'season' => 'Summer',
        'reinforced' => true,
        'three_peak_mountain_snowflake' => false,
    ]);
    $tireLabel = $product->tireLabel()->create([
        'rolling_resistance' => 'B',
        'wet_grip' => 'A',
        'noise_db' => 69,
        'noise_class' => 'A',
    ]);
    $sku = $supplier->supplierSkus()->create([
        'product_id' => $product->id,
        'external_sku' => '0001234567890',
        'source_description' => 'TEST Tire',
        'catalog_state' => CatalogState::Catalog,
    ]);

    expect($product->product_type)->toBe(ProductType::Tire)
        ->and($product->publication_status)->toBe(PublicationStatus::Draft)
        ->and($product->brand->is($brand))->toBeTrue()
        ->and($brand->products->first()->is($product))->toBeTrue()
        ->and($tireSpec->width_mm)->toBe('205.00')
        ->and($tireSpec->reinforced)->toBeTrue()
        ->and($tireLabel->noise_db)->toBe(69)
        ->and($product->tireSpec->is($tireSpec))->toBeTrue()
        ->and($sku->external_sku)->toBe('0001234567890')
        ->and($sku->catalog_state)->toBe(CatalogState::Catalog)
        ->and($sku->supplier->is($supplier))->toBeTrue()
        ->and($sku->product->is($product))->toBeTrue();
});

test('price and stock models keep decimal money and link current state', function (): void {
    $supplier = Supplier::create([
        'code' => 'test-ndi-pricing',
        'name' => 'TEST NDI Pricing',
        'is_active' => true,
    ]);
    $sku = SupplierSku::create([
        'supplier_id' => $supplier->id,
        'product_id' => null,
        'external_sku' => 'TEST-STOCK-ONLY-001',
        'catalog_state' => CatalogState::StockOnly,
    ]);
    $priceList = PriceList::create([
        'supplier_id' => $supplier->id,
        'code' => 'test-net',
        'currency' => 'NOK',
        'tax_mode' => TaxMode::Net,
        'valid_from' => now(),
    ]);
    $artifactId = modelTestArtifact($supplier->id);
    $price = SkuPrice::create([
        'supplier_sku_id' => $sku->id,
        'price_list_id' => $priceList->id,
        'amount' => '100.1250',
        'observed_at' => now(),
        'source_artifact_id' => $artifactId,
    ]);
    $warehouse = Warehouse::create([
        'supplier_id' => $supplier->id,
        'code' => 'vestby',
        'name' => 'TEST Vestby',
        'country_code' => 'NO',
        'is_active' => true,
    ]);
    $stock = CurrentStock::create([
        'supplier_sku_id' => $sku->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 5,
        'observed_at' => now(),
        'source_artifact_id' => $artifactId,
    ]);

    expect($sku->catalog_state)->toBe(CatalogState::StockOnly)
        ->and($sku->product)->toBeNull()
        ->and($priceList->tax_mode)->toBe(TaxMode::Net)
        ->and($price->amount)->toBe('100.1250')
        ->and($price->supplierSku->is($sku))->toBeTrue()
        ->and($price->priceList->is($priceList))->toBeTrue()
        ->and($stock->quantity)->toBe(5)
        ->and($stock->supplierSku->is($sku))->toBeTrue()
        ->and($stock->warehouse->is($warehouse))->toBeTrue()
        ->and($warehouse->currentStock->first()->is($stock))->toBeTrue();
});

function modelTestArtifact(int $supplierId): string
{
    $id = (string) Str::ulid();

    DB::table('source_artifacts')->insert([
        'id' => $id,
        'supplier_id' => $supplierId,
        'kind' => 'xlsx',
        'original_filename' => 'test.xlsx',
        'storage_key' => 'raw/model-test-'.Str::lower(Str::random(8)).'.xlsx',
        'byte_size' => 128,
        'sha256' => hash('sha256', $id),
        'received_at' => now(),
        'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'is_redacted' => true,
    ]);

    return $id;
}
