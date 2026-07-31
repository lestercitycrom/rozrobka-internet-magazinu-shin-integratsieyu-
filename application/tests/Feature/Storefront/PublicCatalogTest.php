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

test('visitor can filter published tires and open a product page', function (): void {
    storefrontProduct(ProductType::Tire, 'SKU-TIRE-205', [
        'width_mm' => '205.00', 'aspect_ratio' => '55.00', 'rim_diameter_in' => '16.00',
        'load_index' => '91', 'speed_symbol' => 'V', 'season' => 'Summer',
    ]);
    storefrontProduct(ProductType::Tire, 'SKU-TIRE-195', [
        'width_mm' => '195.00', 'aspect_ratio' => '65.00', 'rim_diameter_in' => '15.00',
        'load_index' => '91', 'speed_symbol' => 'T', 'season' => 'Winter',
    ]);

    $response = $this->get('/dekk?width=205&aspect=55&rim=16&season=Summer');

    $response->assertOk()
        ->assertSee('SKU-TIRE-205')
        ->assertDontSee('SKU-TIRE-195')
        ->assertSee('value="205"', false)
        ->assertSee('value="Summer" selected', false);

    $this->get('/produkter/SKU-TIRE-205')
        ->assertOk()
        ->assertSee('205/55 R16')
        ->assertSee('Legg i handlekurv kommer snart')
        ->assertSee('SKU-TIRE-205');
});

test('wheel listing exposes a clear empty state and preserves filter query', function (): void {
    storefrontProduct(ProductType::Wheel, 'SKU-WHEEL-18', [
        'width_in' => '8.00', 'diameter_in' => '18.00', 'bolt_holes' => 5, 'pcd_mm' => '112.00',
        'offset_et_mm' => '45.00', 'center_bore_mm' => '66.60',
    ]);

    $this->get('/felger?diameter=19&bolt_holes=5')
        ->assertOk()
        ->assertSee('Ingen produkter passer filtrene')
        ->assertSee('value="19"', false)
        ->assertDontSee('SKU-WHEEL-18');
});

test('russian preview translates catalog and product copy without Norwegian UI strings', function (): void {
    config()->set('storefront.ru_preview_enabled', true);
    storefrontProduct(ProductType::Tire, 'SKU-RU-205', ['width_mm' => '205.00', 'aspect_ratio' => '55.00', 'rim_diameter_in' => '16.00', 'load_index' => '91', 'speed_symbol' => 'V', 'season' => 'Summer']);
    $this->get('/ru/dekk?width=205')->assertOk()->assertSee('Ширина')->assertSee('Открыть товар')->assertDontSee('Bredde')->assertDontSee('Se produkt')->assertDontSee('Varenr.');
    $this->get('/ru/produkter/SKU-RU-205')->assertOk()->assertSee('Артикул')->assertSee('Лето')->assertSee('Добавление в корзину скоро появится')->assertDontSee('Legg i handlekurv kommer snart')->assertDontSee('Varenummer');
});

function storefrontProduct(ProductType $type, string $skuCode, array $spec): SupplierSku
{
    $supplier = Supplier::query()->firstOrCreate(['code' => 'storefront-ndi'], ['name' => 'Storefront NDI', 'is_active' => true]);
    $brand = Brand::query()->firstOrCreate(['canonical_name' => 'Nordlys'], ['slug' => 'nordlys']);
    $product = Product::create([
        'product_type' => $type, 'brand_id' => $brand->id, 'model_name' => 'Arctic', 'title' => 'Nordlys Arctic',
        'publication_status' => PublicationStatus::Published, 'first_seen_at' => now(),
    ]);
    $type === ProductType::Tire ? $product->tireSpec()->create($spec) : $product->wheelSpec()->create($spec);
    $sku = SupplierSku::create(['supplier_id' => $supplier->id, 'product_id' => $product->id, 'external_sku' => $skuCode, 'catalog_state' => CatalogState::Catalog]);
    $artifactId = storefrontArtifact($supplier->id);
    $priceList = PriceList::query()->firstOrCreate(['supplier_id' => $supplier->id, 'code' => 'public', 'currency' => 'NOK', 'tax_mode' => TaxMode::Gross, 'valid_from' => now()]);
    SkuPrice::create(['supplier_sku_id' => $sku->id, 'price_list_id' => $priceList->id, 'amount' => '1499.0000', 'observed_at' => now(), 'source_artifact_id' => $artifactId]);
    $warehouse = Warehouse::query()->firstOrCreate(['supplier_id' => $supplier->id, 'code' => 'tromso'], ['name' => 'Tromsø', 'is_active' => true]);
    CurrentStock::create(['supplier_sku_id' => $sku->id, 'warehouse_id' => $warehouse->id, 'quantity' => 2, 'observed_at' => now(), 'source_artifact_id' => $artifactId]);

    return $sku;
}

function storefrontArtifact(int $supplierId): string
{
    $id = (string) Str::ulid();
    DB::table('source_artifacts')->insert(['id' => $id, 'supplier_id' => $supplierId, 'kind' => 'fixture', 'original_filename' => 'storefront.xlsx', 'storage_key' => 'fixtures/'.Str::lower(Str::random(8)).'.xlsx', 'byte_size' => 1, 'sha256' => hash('sha256', $id), 'received_at' => now(), 'is_redacted' => true]);

    return $id;
}
