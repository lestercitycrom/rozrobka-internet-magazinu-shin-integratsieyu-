<?php

use App\Models\User;
use App\Modules\Cart\CartResolver;
use App\Modules\Cart\SessionCart;
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
use App\Modules\Content\Models\MediaAsset;
use App\Modules\Content\Models\MediaAssignment;
use App\Modules\Pricing\Enums\PricingRuleScope;
use App\Modules\Pricing\Models\PricingRule;
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
        ->assertSee('value="205" selected', false)
        ->assertSee('value="Summer" selected', false)
        ->assertSee('class="et-product-card"', false);

    $this->get('/produkter/SKU-TIRE-205')
        ->assertOk()
        ->assertSee('205/55 R16')
        ->assertSee(__('storefront.price_unavailable'))
        ->assertSee('data-testid="product-price-unavailable"', false)
        ->assertSee('data-testid="product-availability-contact"', false)
        ->assertSee('href="'.route('storefront.content.show', ['slug' => 'kontakt']).'"', false)
        ->assertSee(__('storefront.availability_contact'))
        ->assertDontSee('data-testid="product-cart-form"', false)
        ->assertSee('SKU-TIRE-205');
});

test('catalog cards normalize decimals and never invent a zero tire profile', function (): void {
    storefrontProduct(ProductType::Tire, 'SKU-TIRE-COMMERCIAL', [
        'width_mm' => '205.00', 'aspect_ratio' => '0.00', 'rim_diameter_in' => '16.00',
        'load_index' => '110/108', 'speed_symbol' => 'T', 'season' => 'Summer',
    ]);

    $this->get('/dekk')
        ->assertOk()
        ->assertSee('205 R16 · 110/108T · Sommer')
        ->assertDontSee('205.00/0.00 R16.00')
        ->assertDontSee('205/0 R16');

    $this->get('/produkter/SKU-TIRE-COMMERCIAL')
        ->assertOk()
        ->assertSee('205 R16')
        ->assertDontSee('205/0 R16');
});

test('catalog exposes faceted controls, active filters, stock filter and safe suggestions', function (): void {
    storefrontProduct(ProductType::Tire, 'SKU-FILTER-205', [
        'width_mm' => '205.00', 'aspect_ratio' => '55.00', 'rim_diameter_in' => '16.00',
        'load_index' => '91', 'speed_symbol' => 'V', 'season' => 'Summer',
    ]);
    storefrontProduct(ProductType::Tire, 'SKU-FILTER-195', [
        'width_mm' => '195.00', 'aspect_ratio' => '65.00', 'rim_diameter_in' => '15.00',
        'load_index' => '91', 'speed_symbol' => 'T', 'season' => 'Winter',
    ]);

    $this->get('/dekk?width=205&in_stock=1&sort=title')
        ->assertOk()
        ->assertSee('data-testid="catalog-category-rail"', false)
        ->assertSee('data-testid="catalog-category-tires"', false)
        ->assertSee('data-testid="catalog-category-wheels"', false)
        ->assertSee('data-testid="catalog-category-packages"', false)
        ->assertSee('data-testid="catalog-category-tpms"', false)
        ->assertSee('data-testid="catalog-filter-group-search"', false)
        ->assertSee('data-testid="catalog-filter-group-dimensions"', false)
        ->assertSee('data-testid="catalog-filter-group-availability"', false)
        ->assertSee('data-catalog-autocomplete="'.route('storefront.tires.suggestions').'"', false)
        ->assertSee('list="catalog-suggestions"', false)
        ->assertSee('name="in_stock" value="1" checked', false)
        ->assertSee('option value="title" selected', false)
        ->assertSee('data-testid="active-filters"', false)
        ->assertSee('value="205" selected', false)
        ->assertSee('SKU-FILTER-205')
        ->assertDontSee('SKU-FILTER-195');

    $this->getJson('/dekk/suggestions?q=SKU-FILTER')
        ->assertOk()
        ->assertJsonFragment(['sku' => 'SKU-FILTER-205']);

    $this->getJson('/dekk/suggestions?q=x')->assertOk()->assertExactJson(['suggestions' => []]);
});

test('catalog safely ignores non scalar public filter and autocomplete inputs', function (): void {
    storefrontProduct(ProductType::Tire, 'SKU-SAFE-QUERY', [
        'width_mm' => '205.00', 'aspect_ratio' => '55.00', 'rim_diameter_in' => '16.00',
        'load_index' => '91', 'speed_symbol' => 'V', 'season' => 'Summer',
    ]);

    $this->get('/dekk?q%5B%5D=x&sort%5B%5D=title&width%5B%5D=205')->assertOk()->assertSee('SKU-SAFE-QUERY');
    $this->getJson('/dekk/suggestions?q%5B%5D=x')->assertOk()->assertExactJson(['suggestions' => []]);
});

test('active season and sort chips use localized labels', function (): void {
    config()->set('storefront.ru_preview_enabled', true);
    storefrontProduct(ProductType::Tire, 'SKU-LOCALIZED-CHIP', [
        'width_mm' => '205.00', 'aspect_ratio' => '55.00', 'rim_diameter_in' => '16.00',
        'load_index' => '91', 'speed_symbol' => 'V', 'season' => 'Summer',
    ]);

    $this->get('/dekk?season=Summer&sort=title')
        ->assertOk()
        ->assertSee('Sesong: Sommer')
        ->assertSee('Sorter: Produktnavn');
    $this->get('/ru/dekk?season=Summer&sort=title')
        ->assertOk()
        ->assertSee('Сезон: Лето')
        ->assertSee('Сортировка: По названию');
});

test('wheel listing exposes a clear empty state and preserves filter query', function (): void {
    storefrontProduct(ProductType::Wheel, 'SKU-WHEEL-18', [
        'width_in' => '8.00', 'diameter_in' => '18.00', 'bolt_holes' => 5, 'pcd_mm' => '112.00',
        'offset_et_mm' => '45.00', 'center_bore_mm' => '66.60',
    ]);

    $this->get('/felger?diameter=19&bolt_holes=5')
        ->assertOk()
        ->assertSee('data-testid="catalog-filter-group-wheel-size"', false)
        ->assertSee('data-testid="catalog-filter-group-fitment"', false)
        ->assertSee('Ingen produkter passer filtrene')
        ->assertSee('value="19"', false)
        ->assertDontSee('SKU-WHEEL-18');
});

test('russian preview translates catalog and product copy without Norwegian UI strings', function (): void {
    config()->set('storefront.ru_preview_enabled', true);
    storefrontProduct(ProductType::Tire, 'SKU-RU-205', ['width_mm' => '205.00', 'aspect_ratio' => '55.00', 'rim_diameter_in' => '16.00', 'load_index' => '91', 'speed_symbol' => 'V', 'season' => 'Summer']);
    $this->get('/ru/dekk?width=205')->assertOk()->assertSee('Ширина')->assertSee('Открыть товар')->assertDontSee('Bredde')->assertDontSee('Se produkt')->assertDontSee('Varenr.');
    $this->get('/ru/produkter/SKU-RU-205')->assertOk()->assertSee('Артикул')->assertSee('Лето')->assertDontSee('Varenummer');
});

test('primary product media uses lazy card loading, eager detail loading, russian fallback and no image when absent', function (): void {
    config()->set('storefront.ru_preview_enabled', true);
    $sku = storefrontProduct(ProductType::Tire, 'SKU-MEDIA', ['width_mm' => '205.00', 'aspect_ratio' => '55.00', 'rim_diameter_in' => '16.00', 'load_index' => '91', 'speed_symbol' => 'V', 'season' => 'Summer']);
    $asset = MediaAsset::create(['disk' => 'public', 'path' => 'media/test.png', 'original_name' => 'test.png', 'mime' => 'image/png', 'byte_size' => 1, 'width' => 1, 'height' => 1, 'sha256' => str_repeat('b', 64), 'alt_nb' => 'Fallback alt', 'alt_ru' => null, 'uploader_id' => User::factory()->owner()->create()->id, 'is_active' => true]);
    MediaAssignment::create(['media_asset_id' => $asset->id, 'assignable_type' => Product::class, 'assignable_id' => $sku->product_id, 'role' => 'primary']);
    $this->get('/dekk')
        ->assertSee('data-testid="product-primary-media"', false)
        ->assertSee('data-product-media-fallback', false)
        ->assertSee('loading="lazy"', false);
    $this->get('/produkter/SKU-MEDIA')->assertSee('loading="eager"', false)->assertSee('fetchpriority="high"', false);
    $this->get('/ru/produkter/SKU-MEDIA')->assertSee('alt="Fallback alt"', false);
    storefrontProduct(ProductType::Tire, 'SKU-NO-MEDIA', ['width_mm' => '195.00', 'aspect_ratio' => '65.00', 'rim_diameter_in' => '15.00', 'load_index' => '91', 'speed_symbol' => 'T', 'season' => 'Winter']);
    $this->get('/produkter/SKU-NO-MEDIA')
        ->assertDontSee('data-testid="product-primary-media"', false)
        ->assertSee('data-testid="product-visual-fallback"', false)
        ->assertSee('data-product-fallback-kind="tire"', false)
        ->assertSee('data-testid="product-fallback-blueprint"', false)
        ->assertSee('aria-hidden="true" focusable="false"', false)
        ->assertSee('role="img"', false)
        ->assertSee('Uten produktfoto');
});

test('tire and wheel fallback plates expose distinct localized visual contracts', function (): void {
    config()->set('storefront.ru_preview_enabled', true);
    storefrontProduct(ProductType::Tire, 'SKU-FALLBACK-TIRE', [
        'width_mm' => '205.00', 'aspect_ratio' => '55.00', 'rim_diameter_in' => '16.00',
        'load_index' => '91', 'speed_symbol' => 'V', 'season' => 'Winter',
    ]);
    storefrontProduct(ProductType::Wheel, 'SKU-FALLBACK-WHEEL', [
        'width_in' => '8.00', 'diameter_in' => '18.00', 'bolt_holes' => 5,
        'pcd_mm' => '112.00', 'offset_et_mm' => '45.00', 'center_bore_mm' => '66.60',
    ]);

    $this->get('/produkter/SKU-FALLBACK-TIRE')
        ->assertOk()
        ->assertSee('data-product-fallback-kind="tire"', false)
        ->assertSee('>Dekk</span>', false)
        ->assertSee('Uten produktfoto');
    $this->get('/produkter/SKU-FALLBACK-WHEEL')
        ->assertOk()
        ->assertSee('data-product-fallback-kind="wheel"', false)
        ->assertSee('>Felg</span>', false)
        ->assertSee('Uten produktfoto');
    $this->get('/ru/produkter/SKU-FALLBACK-TIRE')
        ->assertOk()
        ->assertSee('>Шина</span>', false)
        ->assertSee('Без фото товара')
        ->assertDontSee('Uten produktfoto');
    $this->get('/ru/produkter/SKU-FALLBACK-WHEEL')
        ->assertOk()
        ->assertSee('>Диск</span>', false)
        ->assertSee('Без фото товара')
        ->assertDontSee('Uten produktfoto');
});

test('catalog has image filter includes only products with an active primary assignment', function (): void {
    config()->set('storefront.ru_preview_enabled', true);
    $withImage = storefrontProduct(ProductType::Tire, 'SKU-WITH-IMAGE', ['width_mm' => '205.00', 'aspect_ratio' => '55.00', 'rim_diameter_in' => '16.00', 'load_index' => '91', 'speed_symbol' => 'V', 'season' => 'Summer']);
    $inactiveImage = storefrontProduct(ProductType::Tire, 'SKU-INACTIVE-IMAGE', ['width_mm' => '195.00', 'aspect_ratio' => '65.00', 'rim_diameter_in' => '15.00', 'load_index' => '91', 'speed_symbol' => 'T', 'season' => 'Winter']);
    $secondaryImage = storefrontProduct(ProductType::Tire, 'SKU-SECONDARY-IMAGE', ['width_mm' => '225.00', 'aspect_ratio' => '45.00', 'rim_diameter_in' => '18.00', 'load_index' => '95', 'speed_symbol' => 'Y', 'season' => 'Summer']);
    storefrontProduct(ProductType::Tire, 'SKU-FALLBACK-ONLY', ['width_mm' => '215.00', 'aspect_ratio' => '60.00', 'rim_diameter_in' => '17.00', 'load_index' => '94', 'speed_symbol' => 'H', 'season' => 'All season']);
    $uploader = User::factory()->owner()->create();
    $primary = storefrontMediaAsset($uploader, 'primary', true);
    $inactive = storefrontMediaAsset($uploader, 'inactive', false);
    $secondary = storefrontMediaAsset($uploader, 'secondary', true);
    MediaAssignment::create(['media_asset_id' => $primary->id, 'assignable_type' => Product::class, 'assignable_id' => $withImage->product_id, 'role' => 'primary']);
    MediaAssignment::create(['media_asset_id' => $inactive->id, 'assignable_type' => Product::class, 'assignable_id' => $inactiveImage->product_id, 'role' => 'primary']);
    MediaAssignment::create(['media_asset_id' => $secondary->id, 'assignable_type' => Product::class, 'assignable_id' => $secondaryImage->product_id, 'role' => 'gallery']);

    $this->get('/dekk?has_image=1')
        ->assertOk()
        ->assertSee('name="has_image" value="1" checked', false)
        ->assertSee('Kun med produktbilde: Ja')
        ->assertSee('SKU-WITH-IMAGE')
        ->assertDontSee('SKU-INACTIVE-IMAGE')
        ->assertDontSee('SKU-SECONDARY-IMAGE')
        ->assertDontSee('SKU-FALLBACK-ONLY');

    $this->get('/ru/dekk?has_image=1')
        ->assertOk()
        ->assertSee('Только с фото товара')
        ->assertSee('SKU-WITH-IMAGE')
        ->assertDontSee('storefront.has_image');
});

test('product detail presents supplier technical data and EU tyre label', function (): void {
    $sku = storefrontProduct(ProductType::Tire, 'SKU-LABELLED', [
        'width_mm' => '225.00', 'aspect_ratio' => '45.00', 'rim_diameter_in' => '18.00',
        'load_index' => '95', 'speed_symbol' => 'Y', 'season' => 'Summer', 'construction' => 'Radial',
        'reinforced' => true, 'three_peak_mountain_snowflake' => false, 'run_flat' => true,
    ]);
    $sku->product->tireLabel()->create([
        'rolling_resistance' => 'B', 'wet_grip' => 'A', 'noise_db' => 69, 'noise_class' => 'A',
    ]);

    $this->get('/produkter/SKU-LABELLED')
        ->assertOk()
        ->assertSee('Tekniske spesifikasjoner')
        ->assertSee('Forsterket')
        ->assertSee('EU-dekkmerking')
        ->assertSee('69 dB');
});

test('public catalog retains fresh zero stock while keeping inactive suppliers hidden', function (): void {
    config()->set('storefront.ru_preview_enabled', true);
    $local = storefrontProduct(ProductType::Tire, 'SKU-LOCAL-STOCK', [
        'width_mm' => '205.00', 'aspect_ratio' => '55.00', 'rim_diameter_in' => '16.00',
        'load_index' => '91', 'speed_symbol' => 'V', 'season' => 'Summer',
    ]);
    $remote = storefrontProduct(ProductType::Tire, 'SKU-REMOTE-STOCK', [
        'width_mm' => '215.00', 'aspect_ratio' => '55.00', 'rim_diameter_in' => '17.00',
        'load_index' => '94', 'speed_symbol' => 'V', 'season' => 'Summer',
    ]);
    $hidden = storefrontProduct(ProductType::Tire, 'SKU-ZERO-STOCK', [
        'width_mm' => '195.00', 'aspect_ratio' => '65.00', 'rim_diameter_in' => '15.00',
        'load_index' => '91', 'speed_symbol' => 'T', 'season' => 'Winter',
    ]);
    $inactive = storefrontProduct(ProductType::Tire, 'SKU-INACTIVE-SUPPLIER', [
        'width_mm' => '225.00', 'aspect_ratio' => '45.00', 'rim_diameter_in' => '18.00',
        'load_index' => '95', 'speed_symbol' => 'V', 'season' => 'Summer',
    ], 'storefront-inactive');
    DB::table('suppliers')->where('id', $inactive->supplier_id)->update(['is_active' => false]);

    CurrentStock::query()->where('supplier_sku_id', $remote->id)->update(['quantity' => 0]);
    $remoteWarehouse = Warehouse::query()->firstOrCreate(
        ['supplier_id' => $remote->supplier_id, 'code' => 'trondheim'],
        ['name' => 'Trondheim', 'is_active' => true],
    );
    CurrentStock::create([
        'supplier_sku_id' => $remote->id,
        'warehouse_id' => $remoteWarehouse->id,
        'quantity' => 4,
        'observed_at' => now(),
        'source_artifact_id' => storefrontArtifact($remote->supplier_id),
    ]);
    CurrentStock::query()->where('supplier_sku_id', $hidden->id)->update(['quantity' => 0]);
    PricingRule::create([
        'name' => 'Public', 'scope' => PricingRuleScope::Global, 'source_price_list_code' => 'public',
        'currency' => 'NOK', 'markup_basis_points' => 0, 'fixed_markup_minor' => 0,
        'rounding_increment_minor' => 1, 'priority' => 1, 'is_active' => true,
    ]);

    $this->get('/dekk')
        ->assertOk()
        ->assertSee('SKU-LOCAL-STOCK')
        ->assertSee('På lager i Tromsø')
        ->assertSee('SKU-REMOTE-STOCK')
        ->assertSee('Tilgjengelig fra annet NDI-lager')
        ->assertSee('SKU-ZERO-STOCK')
        ->assertSee(__('storefront.stock_none'))
        ->assertDontSee('SKU-INACTIVE-SUPPLIER')
        ->assertSee('value="195"', false)
        ->assertDontSee('value="225"', false);

    $this->get('/dekk?in_stock=1')
        ->assertOk()
        ->assertSee('SKU-LOCAL-STOCK')
        ->assertSee('SKU-REMOTE-STOCK')
        ->assertDontSee('SKU-ZERO-STOCK')
        ->assertDontSee('SKU-INACTIVE-SUPPLIER');

    $this->get('/produkter/SKU-LOCAL-STOCK')->assertOk()->assertSee('2 på lager i Tromsø');
    $this->get('/produkter/SKU-REMOTE-STOCK')->assertOk()->assertSee('På annet NDI-lager');
    $this->get('/produkter/SKU-ZERO-STOCK')
        ->assertOk()
        ->assertSee(__('storefront.stock_none'))
        ->assertSee(__('storefront.availability_contact'))
        ->assertSee('href="'.route('storefront.content.show', ['slug' => 'kontakt']).'"', false)
        ->assertDontSee('data-testid="product-cart-form"', false);
    $this->get('/ru/produkter/SKU-ZERO-STOCK')
        ->assertOk()
        ->assertSee('Нет зарегистрированного остатка.')
        ->assertSee('Уточнить цену и наличие')
        ->assertSee('href="'.route('storefront.preview.content.show', ['slug' => 'kontakt']).'"', false)
        ->assertDontSee('data-testid="product-cart-form"', false);
    $this->get('/produkter/SKU-INACTIVE-SUPPLIER')->assertNotFound();
    $this->getJson('/dekk/suggestions?q=SKU-ZERO')->assertOk()->assertJsonFragment(['sku' => 'SKU-ZERO-STOCK']);
    $this->getJson('/dekk/suggestions?q=SKU-INACTIVE')->assertOk()->assertExactJson(['suggestions' => []]);
    $this->get('/sitemap.xml')->assertSee('SKU-ZERO-STOCK')->assertDontSee('SKU-INACTIVE-SUPPLIER')->assertSee('SKU-LOCAL-STOCK');
});

test('all public catalog surfaces retain stale and missing stock but purchasing fails closed', function (): void {
    config()->set('storefront.ru_preview_enabled', true);
    storefrontProduct(ProductType::Tire, 'SKU-FRESH-STOCK', [
        'width_mm' => '205.00', 'aspect_ratio' => '55.00', 'rim_diameter_in' => '16.00',
        'load_index' => '91', 'speed_symbol' => 'V', 'season' => 'Summer',
    ]);
    $stale = storefrontProduct(ProductType::Tire, 'SKU-STALE-STOCK', [
        'width_mm' => '305.00', 'aspect_ratio' => '30.00', 'rim_diameter_in' => '21.00',
        'load_index' => '104', 'speed_symbol' => 'Y', 'season' => 'Summer',
    ]);
    $staleBrand = Brand::create(['canonical_name' => 'StaleOnly', 'slug' => 'stale-only']);
    $stale->product()->update(['brand_id' => $staleBrand->id]);
    $missing = storefrontProduct(ProductType::Tire, 'SKU-MISSING-STOCK', [
        'width_mm' => '315.00', 'aspect_ratio' => '30.00', 'rim_diameter_in' => '22.00',
        'load_index' => '105', 'speed_symbol' => 'Y', 'season' => 'Summer',
    ]);
    $missingBrand = Brand::create(['canonical_name' => 'MissingOnly', 'slug' => 'missing-only']);
    $missing->product()->update(['brand_id' => $missingBrand->id]);
    PricingRule::create([
        'name' => 'Public', 'scope' => PricingRuleScope::Global, 'source_price_list_code' => 'public',
        'currency' => 'NOK', 'markup_basis_points' => 0, 'fixed_markup_minor' => 0,
        'rounding_increment_minor' => 1, 'priority' => 1, 'is_active' => true,
    ]);
    config()->set('storefront.stock_max_age_minutes', 180);
    CurrentStock::query()->where('supplier_sku_id', $stale->id)->update(['observed_at' => now()->subMinutes(181)]);
    CurrentStock::query()->where('supplier_sku_id', $missing->id)->delete();

    $this->get('/dekk')
        ->assertOk()
        ->assertSee('SKU-FRESH-STOCK')
        ->assertSee('SKU-STALE-STOCK')
        ->assertSee('SKU-MISSING-STOCK')
        ->assertSee('StaleOnly')
        ->assertSee('MissingOnly')
        ->assertSee('value="305"', false)
        ->assertSee('value="315"', false)
        ->assertSee(__('storefront.stock_stale'))
        ->assertSee(__('storefront.stock_missing'));
    $this->get('/dekk?in_stock=1')
        ->assertOk()
        ->assertSee('SKU-FRESH-STOCK')
        ->assertDontSee('SKU-STALE-STOCK')
        ->assertDontSee('SKU-MISSING-STOCK');
    $this->get('/produkter/SKU-FRESH-STOCK')
        ->assertOk()
        ->assertSee('data-testid="product-cart-form"', false)
        ->assertDontSee('data-testid="product-availability-contact"', false);
    $this->get('/produkter/SKU-STALE-STOCK')
        ->assertOk()
        ->assertSee(__('storefront.stock_stale_detail'))
        ->assertSee(__('storefront.availability_contact'))
        ->assertSee('href="'.route('storefront.content.show', ['slug' => 'kontakt']).'"', false)
        ->assertDontSee('data-testid="product-price-unavailable"', false)
        ->assertDontSee('data-testid="product-cart-form"', false);
    $this->get('/produkter/SKU-MISSING-STOCK')
        ->assertOk()
        ->assertSee(__('storefront.stock_missing_detail'))
        ->assertSee(__('storefront.availability_contact'))
        ->assertSee('href="'.route('storefront.content.show', ['slug' => 'kontakt']).'"', false)
        ->assertDontSee('data-testid="product-price-unavailable"', false)
        ->assertDontSee('data-testid="product-cart-form"', false);
    $this->get('/ru/produkter/SKU-STALE-STOCK')
        ->assertOk()
        ->assertSee('Последние данные об остатках устарели и не используются для оформления.')
        ->assertSee('Уточнить цену и наличие')
        ->assertSee('href="'.route('storefront.preview.content.show', ['slug' => 'kontakt']).'"', false)
        ->assertDontSee('data-testid="product-cart-form"', false);
    $this->get('/ru/produkter/SKU-MISSING-STOCK')
        ->assertOk()
        ->assertSee('NDI ещё не передал складской остаток для этого товара.')
        ->assertSee('Уточнить цену и наличие')
        ->assertSee('href="'.route('storefront.preview.content.show', ['slug' => 'kontakt']).'"', false)
        ->assertDontSee('data-testid="product-cart-form"', false);
    $this->getJson('/dekk/suggestions?q=SKU-FRESH')->assertOk()->assertJsonFragment(['sku' => 'SKU-FRESH-STOCK']);
    $this->getJson('/dekk/suggestions?q=SKU-STALE')->assertOk()->assertJsonFragment(['sku' => 'SKU-STALE-STOCK']);
    $this->getJson('/dekk/suggestions?q=SKU-MISSING')->assertOk()->assertJsonFragment(['sku' => 'SKU-MISSING-STOCK']);
    $this->get('/sitemap.xml')->assertSee('SKU-FRESH-STOCK')->assertSee('SKU-STALE-STOCK')->assertSee('SKU-MISSING-STOCK');

    $sessionCart = app(SessionCart::class);
    $sessionCart->add('SKU-STALE-STOCK', 1);
    $sessionCart->add('SKU-MISSING-STOCK', 1);
    $cart = app(CartResolver::class)->resolve();
    expect($cart->lines)->toBe([])
        ->and($cart->issues)->toHaveCount(2)
        ->and(collect($cart->issues)->pluck('reason')->all())->toEqualCanonicalizing(['stock_stale', 'stock_missing'])
        ->and($cart->isCheckoutReady())->toBeFalse();

    $this->withSession(['storefront.cart.lines' => ['SKU-STALE-STOCK' => 1]])
        ->get('/kasse')
        ->assertRedirect('/handlekurv')
        ->assertSessionHas('cart_error');
});

test('product detail normalizes confirmed tire data and hides uncontracted supplier text in NB and RU', function (): void {
    config()->set('storefront.ru_preview_enabled', true);
    storefrontProduct(ProductType::Tire, '063965255551871001', [
        'width_mm' => '255.00', 'aspect_ratio' => '55.00', 'rim_diameter_in' => '18.00',
        'load_index' => '109', 'speed_symbol' => 'Y', 'season' => 'Summer',
        'construction' => 'PCR radial tyre > 240 km/h', 'winter_type' => 'Winter tyre type', 'production_year' => 'DOT22',
    ]);

    $norwegian = $this->get('/produkter/063965255551871001')->assertOk();
    $norwegian
        ->assertSee('255/55 R18')
        ->assertSee('Radialdekk')
        ->assertSee('2022')
        ->assertDontSee('255.00/55.00 R18.00')
        ->assertDontSee('PCR radial tyre > 240 km/h')
        ->assertDontSee('Winter tyre type')
        ->assertDontSee('Vintertype');
    expect(activeCatalogNavigationVariants($norwegian->getContent(), route('storefront.store.index'), 'Dekk og felger'))
        ->toBe(['desktop' => 1, 'mobile' => 1]);

    $russian = $this->get('/ru/produkter/063965255551871001')->assertOk();
    $russian
        ->assertSee('255/55 R18')
        ->assertSee('Радиальная шина')
        ->assertSee('2022')
        ->assertDontSee('PCR radial tyre > 240 km/h')
        ->assertDontSee('Winter tyre type')
        ->assertDontSee('Тип зимней шины');
    expect(activeCatalogNavigationVariants($russian->getContent(), route('storefront.preview.store.index'), 'Шины и диски'))
        ->toBe(['desktop' => 1, 'mobile' => 1]);
});

test('wheel detail normalizes fitment values and marks the wheel navigation in NB and RU', function (): void {
    config()->set('storefront.ru_preview_enabled', true);
    storefrontProduct(ProductType::Wheel, 'SKU-WHEEL-DETAIL', [
        'width_in' => '8.00', 'diameter_in' => '18.00', 'bolt_holes' => 5,
        'pcd_mm' => '112.00', 'offset_et_mm' => '45.00', 'center_bore_mm' => '66.60',
        'color' => 'Silver', 'bolt_hole_boring' => 'Supplier drilling text',
    ]);

    $norwegian = $this->get('/produkter/SKU-WHEEL-DETAIL')->assertOk();
    $norwegian
        ->assertSee('8 × 18')
        ->assertSee('5×112')
        ->assertSee('66.6')
        ->assertSee(__('storefront.price_unavailable'))
        ->assertSee('data-testid="product-price-unavailable"', false)
        ->assertSee(__('storefront.availability_contact'))
        ->assertSee('href="'.route('storefront.content.show', ['slug' => 'kontakt']).'"', false)
        ->assertDontSee('8.00')
        ->assertDontSee('112.00')
        ->assertDontSee('Silver')
        ->assertDontSee('Supplier drilling text');
    expect(activeCatalogNavigationVariants($norwegian->getContent(), route('storefront.store.index'), 'Dekk og felger'))
        ->toBe(['desktop' => 1, 'mobile' => 1]);

    $russian = $this->get('/ru/produkter/SKU-WHEEL-DETAIL')->assertOk();
    $russian
        ->assertSee('8 × 18')
        ->assertSee('5×112')
        ->assertSee('66.6')
        ->assertSee('Цена для интернет-заказа пока не настроена')
        ->assertSee('Уточнить цену и наличие')
        ->assertSee('href="'.route('storefront.preview.content.show', ['slug' => 'kontakt']).'"', false)
        ->assertDontSee('Silver');
    expect(activeCatalogNavigationVariants($russian->getContent(), route('storefront.preview.store.index'), 'Шины и диски'))
        ->toBe(['desktop' => 1, 'mobile' => 1]);
});

function storefrontProduct(ProductType $type, string $skuCode, array $spec, string $supplierCode = 'storefront-ndi'): SupplierSku
{
    $supplier = Supplier::query()->firstOrCreate(['code' => $supplierCode], ['name' => 'Storefront NDI', 'is_active' => true]);
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

function storefrontMediaAsset(User $uploader, string $suffix, bool $active): MediaAsset
{
    return MediaAsset::create([
        'disk' => 'public', 'path' => "media/{$suffix}.png", 'original_name' => "{$suffix}.png",
        'mime' => 'image/png', 'byte_size' => 1, 'width' => 1, 'height' => 1,
        'sha256' => hash('sha256', $suffix), 'alt_nb' => 'Produktbilde', 'alt_ru' => 'Фото товара',
        'uploader_id' => $uploader->id, 'is_active' => $active,
    ]);
}

/** @return array{desktop: int, mobile: int} */
function activeCatalogNavigationVariants(string $html, string $href, string $label): array
{
    $anchor = '<a\b(?=[^>]*\bhref="'.preg_quote($href, '/').'\")(?=[^>]*\baria-current="page")[^>]*>';
    $escapedLabel = preg_quote($label, '/');

    return [
        'desktop' => preg_match_all('/'.$anchor.'\s*'.$escapedLabel.'\s*<\/a>/u', $html),
        'mobile' => preg_match_all('/'.$anchor.'\s*<span class="et-mobile-menu__label">\s*'.$escapedLabel.'\s*<\/span>\s*<\/a>/u', $html),
    ];
}
