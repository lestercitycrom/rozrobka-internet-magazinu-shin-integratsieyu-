<?php

use App\Models\User;
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
use App\Modules\Catalog\ReadModel\CatalogQuery;
use App\Modules\Catalog\ReadModel\CatalogReadService;
use App\Modules\Content\Models\MediaAsset;
use App\Modules\Content\Models\MediaAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('it filters public tires and wheels while preserving leading-zero SKUs', function (): void {
    publicCatalogSku('000205550016', ProductType::Tire, ['width_mm' => '205.00', 'aspect_ratio' => '55.00', 'rim_diameter_in' => '16.00', 'season' => 'Summer']);
    publicCatalogSku('WHEEL-01', ProductType::Wheel, ['width_in' => '7.50', 'diameter_in' => '17.00', 'bolt_holes' => 5, 'pcd_mm' => '112.00', 'offset_et_mm' => '35.00', 'center_bore_mm' => '66.60']);

    $service = app(CatalogReadService::class);
    $tires = $service->search(new CatalogQuery(productType: ProductType::Tire, tireWidthMm: '205.00', tireSeason: 'Summer'));
    $wheels = $service->search(new CatalogQuery(productType: ProductType::Wheel, wheelBoltHoles: 5, wheelPcdMm: '112.00'));

    expect($tires->items)->toHaveCount(1)
        ->and($tires->items[0]->sku)->toBe('000205550016')
        ->and($tires->items[0]->tire)->toMatchArray(['season' => 'Summer', 'width_mm' => '205.00'])
        ->and($wheels->items)->toHaveCount(1)
        ->and($wheels->items[0]->wheel)->toMatchArray(['bolt_holes' => 5, 'pcd_mm' => '112.00']);

    expect($service->findPublicSku('000205550016')?->sku)->toBe('000205550016')
        ->and($service->findPublicSku('205550016'))->toBeNull();
});

test('it excludes unpublished and quarantined catalog rows', function (): void {
    publicCatalogSku('PUBLIC-01');
    publicCatalogSku('DRAFT-01', publication: PublicationStatus::Draft);
    publicCatalogSku('HIDDEN-01', publication: PublicationStatus::Hidden);
    publicCatalogSku('QUARANTINE-01', catalogState: CatalogState::NeedsEnrichment);
    publicCatalogSku('STOCK-ONLY-01', catalogState: CatalogState::StockOnly);
    publicCatalogSku('INACTIVE-SUPPLIER-01', supplierActive: false);

    $result = app(CatalogReadService::class)->search(new CatalogQuery(search: '01'));

    expect($result->total)->toBe(1)
        ->and($result->items[0]->sku)->toBe('PUBLIC-01');
});

test('it filters to an active primary product image without duplicating catalog rows', function (): void {
    $withImage = publicCatalogSku('IMAGE-01');
    publicCatalogSku('IMAGE-02');
    $uploader = User::factory()->operations()->create();
    $asset = MediaAsset::create([
        'disk' => 'public', 'path' => 'media/catalog-read.png', 'original_name' => 'catalog-read.png',
        'mime' => 'image/png', 'byte_size' => 1, 'width' => 1, 'height' => 1,
        'sha256' => hash('sha256', 'catalog-read-image'), 'alt_nb' => 'Bilde', 'alt_ru' => 'Фото',
        'uploader_id' => $uploader->id, 'is_active' => true,
    ]);
    MediaAssignment::create(['media_asset_id' => $asset->id, 'assignable_type' => Product::class, 'assignable_id' => $withImage->product_id, 'role' => 'primary']);

    $result = app(CatalogReadService::class)->search(new CatalogQuery(hasImageOnly: true));

    expect($result->total)->toBe(1)
        ->and($result->items)->toHaveCount(1)
        ->and($result->items[0]->sku)->toBe('IMAGE-01')
        ->and($result->items[0]->primaryMedia['url'])->toBe('/storage/media/catalog-read.png');
});

test('it paginates and hydrates prices and stock in a bounded number of queries', function (): void {
    $first = publicCatalogSku('PAGE-01', withStock: false);
    publicCatalogSku('PAGE-02');
    publicCatalogSku('PAGE-03');
    addPublicPriceAndStock($first);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $page = app(CatalogReadService::class)->search(new CatalogQuery(page: 2, perPage: 1));
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($page->total)->toBe(3)
        ->and($page->page)->toBe(2)
        ->and($page->items)->toHaveCount(1)
        // Count, page, price, stock, and one batched primary-media query.
        // The bound must stay constant as the page size grows.
        ->and(count($queries))->toBeLessThanOrEqual(5);

    $priced = app(CatalogReadService::class)->findPublicSku('PAGE-01');
    $pricesByTaxMode = collect($priced?->prices)->keyBy(fn ($price) => $price->taxMode->value);
    expect($priced?->prices)->toHaveCount(2)
        ->and($pricesByTaxMode->keys()->all())->toEqualCanonicalizing([TaxMode::Gross->value, TaxMode::Net->value])
        ->and($pricesByTaxMode->get(TaxMode::Gross->value)?->currency)->toBe('NOK')
        ->and($pricesByTaxMode->get(TaxMode::Gross->value)?->amount)->toBe('123.4500')
        ->and($pricesByTaxMode->get(TaxMode::Net->value)?->amount)->toBe('75.0000')
        ->and($priced?->stock->quantity)->toBe(40);
});

function publicCatalogSku(
    string $externalSku,
    ProductType $type = ProductType::Tire,
    array $spec = [],
    PublicationStatus $publication = PublicationStatus::Published,
    CatalogState $catalogState = CatalogState::Catalog,
    bool $supplierActive = true,
    bool $withStock = true,
): SupplierSku {
    $suffix = Str::lower(Str::random(8));
    $supplier = Supplier::create(['code' => "supplier-{$suffix}", 'name' => 'Catalog test supplier', 'is_active' => $supplierActive]);
    $brand = Brand::create(['canonical_name' => "Brand {$suffix}", 'slug' => "brand-{$suffix}"]);
    $product = Product::create([
        'product_type' => $type, 'brand_id' => $brand->id, 'model_name' => 'Test model', 'title' => "Test {$externalSku}",
        'publication_status' => $publication, 'first_seen_at' => now(),
    ]);
    if ($type === ProductType::Tire) {
        $product->tireSpec()->create($spec + ['width_mm' => '195.00', 'aspect_ratio' => '65.00', 'rim_diameter_in' => '15.00', 'load_index' => '91', 'speed_symbol' => 'T', 'season' => 'Summer']);
    } else {
        $product->wheelSpec()->create($spec + ['width_in' => '6.50', 'diameter_in' => '16.00', 'bolt_holes' => 5, 'pcd_mm' => '112.00', 'offset_et_mm' => '40.00', 'center_bore_mm' => '57.10']);
    }

    $sku = SupplierSku::create(['supplier_id' => $supplier->id, 'product_id' => $product->id, 'external_sku' => $externalSku, 'catalog_state' => $catalogState]);
    if ($withStock) {
        addPublicStock($sku, 1);
    }

    return $sku;
}

function addPublicStock(SupplierSku $sku, int $quantity): void
{
    $artifactId = (string) Str::ulid();
    DB::table('source_artifacts')->insert(['id' => $artifactId, 'supplier_id' => $sku->supplier_id, 'kind' => 'fixture', 'original_filename' => 'stock.xlsx', 'storage_key' => "fixtures/{$artifactId}.xlsx", 'byte_size' => 1, 'sha256' => hash('sha256', $artifactId), 'received_at' => now(), 'is_redacted' => true]);
    $warehouse = Warehouse::create(['supplier_id' => $sku->supplier_id, 'code' => 'norway', 'name' => 'National availability', 'is_active' => true]);
    CurrentStock::create(['supplier_sku_id' => $sku->id, 'warehouse_id' => $warehouse->id, 'quantity' => $quantity, 'observed_at' => now(), 'source_artifact_id' => $artifactId]);
}

function addPublicPriceAndStock(SupplierSku $sku): void
{
    $artifactId = (string) Str::ulid();
    DB::table('source_artifacts')->insert(['id' => $artifactId, 'supplier_id' => $sku->supplier_id, 'kind' => 'fixture', 'original_filename' => 'read-model.xlsx', 'storage_key' => "fixtures/{$artifactId}.xlsx", 'byte_size' => 1, 'sha256' => hash('sha256', $artifactId), 'received_at' => now(), 'is_redacted' => true]);
    $list = PriceList::create(['supplier_id' => $sku->supplier_id, 'code' => 'test-source', 'currency' => 'NOK', 'tax_mode' => TaxMode::Gross, 'valid_from' => now()]);
    SkuPrice::create(['supplier_sku_id' => $sku->id, 'price_list_id' => $list->id, 'amount' => '123.4500', 'observed_at' => now(), 'source_artifact_id' => $artifactId]);
    $netList = PriceList::create(['supplier_id' => $sku->supplier_id, 'code' => 'test-source', 'currency' => 'NOK', 'tax_mode' => TaxMode::Net, 'valid_from' => now()]);
    SkuPrice::create(['supplier_sku_id' => $sku->id, 'price_list_id' => $netList->id, 'amount' => '75.0000', 'observed_at' => now(), 'source_artifact_id' => $artifactId]);
    $national = Warehouse::create(['supplier_id' => $sku->supplier_id, 'code' => 'norway', 'name' => 'National availability', 'is_active' => true]);
    $vestby = Warehouse::create(['supplier_id' => $sku->supplier_id, 'code' => 'vestby', 'name' => 'Vestby', 'is_active' => true]);
    $trondheim = Warehouse::create(['supplier_id' => $sku->supplier_id, 'code' => 'trondheim', 'name' => 'Trondheim', 'is_active' => true]);
    CurrentStock::create(['supplier_sku_id' => $sku->id, 'warehouse_id' => $national->id, 'quantity' => 40, 'observed_at' => now(), 'source_artifact_id' => $artifactId]);
    CurrentStock::create(['supplier_sku_id' => $sku->id, 'warehouse_id' => $vestby->id, 'quantity' => 32, 'observed_at' => now(), 'source_artifact_id' => $artifactId]);
    CurrentStock::create(['supplier_sku_id' => $sku->id, 'warehouse_id' => $trondheim->id, 'quantity' => 40, 'observed_at' => now(), 'source_artifact_id' => $artifactId]);
}
