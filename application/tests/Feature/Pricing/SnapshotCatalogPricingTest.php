<?php

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
use App\Modules\Catalog\ReadModel\CatalogItem;
use App\Modules\Catalog\ReadModel\CatalogPrice;
use App\Modules\Catalog\ReadModel\CatalogReadService;
use App\Modules\Catalog\ReadModel\CatalogStock;
use App\Modules\NdiApi\NdiSandboxCatalogManager;
use App\Modules\NdiApi\NdiSandboxContract;
use App\Modules\Pricing\Enums\PricingRuleScope;
use App\Modules\Pricing\Models\PricingRule;
use App\Modules\Pricing\SellingPriceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-09-16T12:00:00Z'));
    config()->set([
        'ndi.catalog_mode' => 'snapshot',
        'storefront.price_max_age_minutes' => 60,
        'storefront.stock_max_age_minutes' => 60,
        'storefront.ru_preview_enabled' => true,
    ]);
});

afterEach(function (): void {
    $this->travelBack();
});

test('snapshot displays a dated historical price through the approved active rule only', function (): void {
    $item = snapshotPricingItem('2026-03-01T12:00:00Z');
    $service = app(SellingPriceService::class);
    expect($service->forDisplay($item))->toBeNull();

    $rule = snapshotPricingRule(['markup_basis_points' => 1000, 'fixed_markup_minor' => 500]);
    $price = $service->forDisplay($item);

    expect($price?->isSnapshot)->toBeTrue()
        ->and($price?->money->minor)->toBe(110500)
        ->and($price?->sourceMoney->minor)->toBe(100000)
        ->and($price?->sourceObservedAt)->toBe('2026-03-01T12:00:00Z')
        ->and($price?->ruleId)->toBe($rule->id)
        ->and($service->forItem($item))->toBeNull();

    $rule->update(['is_active' => false]);
    expect($service->forDisplay($item))->toBeNull();
});

test('snapshot never returns commercial prices for real items even with fresh stock and prices', function (): void {
    snapshotPricingRule();
    $item = snapshotPricingItem(now()->toISOString());
    $service = app(SellingPriceService::class);

    expect($item->stock->canPurchase())->toBeTrue()
        ->and($service->forItem($item))->toBeNull()
        ->and($service->forItems([$item, $item]))->toBe([null, null])
        ->and($service->forDisplay($item)?->isSnapshot)->toBeTrue();
});

test('snapshot batch display loads matching rules once and retains display-only tags', function (): void {
    snapshotPricingRule();
    DB::flushQueryLog();
    DB::enableQueryLog();
    $prices = app(SellingPriceService::class)->forDisplayItems([
        snapshotPricingItem('2026-03-01 12:00:00'),
        snapshotPricingItem(now()->toISOString()),
    ]);
    $queries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'pricing_rules'));
    DB::disableQueryLog();

    expect($prices)->toHaveCount(2)
        ->and($prices[0]?->money->minor)->toBe(100000)
        ->and($prices[0]?->isSnapshot)->toBeTrue()
        ->and($prices[1]?->isSnapshot)->toBeTrue()
        ->and($queries)->toHaveCount(1);
});

test('historical display rejects missing malformed normalized relative and future source dates', function (string $observedAt): void {
    snapshotPricingRule();
    expect(app(SellingPriceService::class)->forDisplay(snapshotPricingItem($observedAt)))->toBeNull();
})->with([
    'empty' => [''],
    'whitespace' => ['   '],
    'malformed' => ['not-a-date'],
    'invalid calendar day' => ['2026-02-30T12:00:00Z'],
    'zero date' => ['0000-00-00 00:00:00'],
    'relative date' => ['yesterday'],
    'future beyond clock allowance' => ['2026-09-16T12:06:00Z'],
    'future within live clock allowance' => ['2026-09-16T12:01:00Z'],
]);

test('catalog price contract rejects a null observation before pricing', function (): void {
    expect(fn () => new CatalogPrice('1000.0000', 'NOK', TaxMode::Gross, null, 'public'))->toThrow(TypeError::class);
});

test('historical display never turns a zero negative or malformed source into a price via markup', function (string $amount): void {
    snapshotPricingRule(['fixed_markup_minor' => 500]);
    expect(app(SellingPriceService::class)->forDisplay(snapshotPricingItem('2026-03-01T12:00:00Z', $amount)))->toBeNull();
})->with(['0', '0.0000', '-100.00', 'unknown', '1,000.00']);

test('snapshot preserves explicit gross-first net-fallback pricing without bypassing an invalid gross source', function (): void {
    snapshotPricingRule(['source_tax_mode' => TaxMode::Gross, 'priority' => 10]);
    snapshotPricingRule(['name' => 'Net fallback', 'source_tax_mode' => TaxMode::Net, 'markup_basis_points' => 2500, 'priority' => 1]);
    $item = snapshotPricingItem('2026-03-01T12:00:00Z');
    $netSource = new CatalogPrice('800.0000', 'NOK', TaxMode::Net, '2026-03-01T12:00:00Z', 'public');
    $withPrices = fn (array $prices): CatalogItem => new CatalogItem(
        sku: $item->sku, productType: $item->productType, brand: $item->brand,
        modelName: $item->modelName, title: $item->title, prices: $prices,
        stock: $item->stock, tire: $item->tire, wheel: null,
    );
    $service = app(SellingPriceService::class);

    expect($service->forDisplay($withPrices([...$item->prices, $netSource]))?->sourceMoney->minor)->toBe(100000)
        ->and($service->forDisplay($withPrices([$netSource]))?->money->minor)->toBe(100000)
        ->and($service->forDisplay($withPrices([
            new CatalogPrice('0.0000', 'NOK', TaxMode::Gross, '2026-03-01T12:00:00Z', 'public'), $netSource,
        ])))->toBeNull();
});

test('normal live mode retains strict freshness and never tags prices as snapshots', function (): void {
    config()->set('ndi.catalog_mode', 'live');
    snapshotPricingRule();
    $service = app(SellingPriceService::class);
    $fresh = snapshotPricingItem(now()->toISOString());
    $stale = snapshotPricingItem('2026-03-01T12:00:00Z');

    expect($service->forItem($fresh)?->isSnapshot)->toBeFalse()
        ->and($service->forDisplay($fresh)?->isSnapshot)->toBeFalse()
        ->and($service->forItem($stale))->toBeNull()
        ->and($service->forDisplay($stale))->toBeNull();
});

test('production misconfigured snapshot mode fails closed and only explicit live mode restores fresh pricing', function (): void {
    $this->app['env'] = 'production';
    snapshotPricingRule();
    $service = app(SellingPriceService::class);
    $fresh = snapshotPricingItem(now()->toISOString());
    $stale = snapshotPricingItem('2026-03-01T12:00:00Z');

    expect($service->forItem($fresh))->toBeNull()
        ->and($service->forDisplay($fresh))->toBeNull()
        ->and($service->forDisplay($stale))->toBeNull();

    config()->set('ndi.catalog_mode', 'live');
    expect($service->forItem($fresh)?->isSnapshot)->toBeFalse()
        ->and($service->forDisplay($fresh)?->isSnapshot)->toBeFalse()
        ->and($service->forDisplay($stale))->toBeNull();
});

test('unknown catalog mode fails closed for commercial and display prices', function (): void {
    config()->set('ndi.catalog_mode', 'snapshots');
    snapshotPricingRule();
    $item = snapshotPricingItem(now()->toISOString());

    expect(app(SellingPriceService::class)->forItem($item))->toBeNull()
        ->and(app(SellingPriceService::class)->forDisplay($item))->toBeNull();
});

test('snapshot catalog renders localized historical prices and dates without purchase controls or offers', function (string $prefix, string $locale): void {
    $sku = snapshotStorefrontProduct('2026-03-01 12:00:00');
    snapshotPricingRule();
    $before = DB::table('sku_prices')->where('supplier_sku_id', $sku->id)->value('observed_at');

    $this->get($prefix.'/dekk')->assertOk()
        ->assertSee('1 000,00 kr')
        ->assertSee('data-testid="snapshot-catalog-notice"', false)
        ->assertSee(__('storefront.snapshot_catalog_notice', [], $locale))
        ->assertSee('data-testid="snapshot-price-note"', false)
        ->assertSee(__('storefront.snapshot_price_label', ['date' => '01.03.2026'], $locale))
        ->assertDontSee('data-testid="catalog-add-to-cart"', false);

    $this->get($prefix.'/produkter/'.$sku->external_sku)->assertOk()
        ->assertSee('1 000,00 kr')
        ->assertSee('data-testid="snapshot-price-note"', false)
        ->assertSee(__('storefront.snapshot_price_note', ['date' => '01.03.2026'], $locale))
        ->assertDontSee('data-testid="product-price-unavailable"', false)
        ->assertDontSee('data-testid="product-cart-form"', false)
        ->assertDontSee('"@type":"Offer"', false);

    expect(DB::table('sku_prices')->where('supplier_sku_id', $sku->id)->value('observed_at'))->toBe($before);
})->with(['Norwegian' => ['', 'nb'], 'Russian' => ['/ru', 'ru']]);

test('snapshot blocks forged fresh-stock cart add update and checkout independently of display', function (): void {
    $sku = snapshotStorefrontProduct(now()->toDateTimeString());
    snapshotPricingRule();
    $item = app(CatalogReadService::class)->findPublicSku($sku->external_sku);
    expect($item?->stock->canPurchase())->toBeTrue()
        ->and(app(SellingPriceService::class)->forDisplay($item)?->isSnapshot)->toBeTrue();

    $this->get('/produkter/'.$sku->external_sku)->assertOk()
        ->assertDontSee('data-testid="product-cart-form"', false)
        ->assertDontSee('"@type":"Offer"', false);
    $this->from('/dekk')->post('/handlekurv', ['sku' => $sku->external_sku, 'quantity' => 1, 'intent' => 'checkout'])
        ->assertRedirect('/dekk')
        ->assertSessionHas('cart_error', __('commerce.item_not_purchasable'))
        ->assertSessionMissing('storefront.cart.lines');
    $this->from('/dekk')->patch('/handlekurv', ['sku' => $sku->external_sku, 'quantity' => 1])
        ->assertRedirect('/dekk')
        ->assertSessionHas('cart_error', __('commerce.item_not_purchasable'))
        ->assertSessionMissing('storefront.cart.lines');

    app(SessionCart::class)->add($sku->external_sku, 1);
    $cart = app(CartResolver::class)->resolve();
    expect($cart->lines)->toBe([])
        ->and($cart->issues[0]->reason)->toBe('price_unavailable')
        ->and($cart->isCheckoutReady())->toBeFalse();
    $this->get('/kasse')->assertRedirect('/handlekurv')->assertSessionHas('cart_error');
});

test('snapshot keeps the approved sandbox product price and cart behavior unchanged', function (): void {
    config()->set([
        'ndi_api.sandbox.enabled' => true,
        'ndi_api.sandbox.party_id' => NdiSandboxContract::PARTY_ID,
        'ndi_api.sandbox.article_id' => NdiSandboxContract::ARTICLE_ID,
        'ndi_api.sandbox.catalog_sku' => NdiSandboxContract::CATALOG_SKU,
        'ndi_api.sandbox.quantity' => NdiSandboxContract::QUANTITY,
        'ndi_api.sandbox.price_minor' => NdiSandboxContract::UNIT_PRICE_MINOR,
    ]);
    app(NdiSandboxCatalogManager::class)->refresh();
    $item = app(CatalogReadService::class)->findPublicSku(NdiSandboxContract::CATALOG_SKU);
    $service = app(SellingPriceService::class);

    expect($service->forItem($item)?->money->minor)->toBe(NdiSandboxContract::UNIT_PRICE_MINOR)
        ->and($service->forDisplay($item)?->isSnapshot)->toBeFalse();
    $this->get('/produkter/'.NdiSandboxContract::CATALOG_SKU)->assertOk()
        ->assertSee('data-testid="product-cart-form"', false)
        ->assertDontSee('data-testid="snapshot-price-note"', false);
    $this->post('/handlekurv', ['sku' => NdiSandboxContract::CATALOG_SKU, 'quantity' => NdiSandboxContract::QUANTITY, 'intent' => 'cart'])
        ->assertRedirect()
        ->assertSessionHas('storefront.cart.lines', [NdiSandboxContract::CATALOG_SKU => NdiSandboxContract::QUANTITY]);
});

function snapshotPricingRule(array $attributes = []): PricingRule
{
    return PricingRule::create([
        'name' => 'Snapshot approved gross rule', 'scope' => PricingRuleScope::Global,
        'source_price_list_code' => 'public', 'source_tax_mode' => TaxMode::Gross, 'currency' => 'NOK',
        'markup_basis_points' => 0, 'fixed_markup_minor' => 0, 'rounding_increment_minor' => 1,
        'priority' => 100, 'is_active' => true, ...$attributes,
    ]);
}

function snapshotPricingItem(string $observedAt, string $amount = '1000.0000'): CatalogItem
{
    return new CatalogItem(
        sku: 'SNAPSHOT-TIRE', productType: ProductType::Tire, brand: 'Nordlys', modelName: 'Arctic', title: 'Nordlys Arctic',
        prices: [new CatalogPrice($amount, 'NOK', TaxMode::Gross, $observedAt, 'public')],
        stock: new CatalogStock(8, now()->toISOString(), localQuantity: 8),
        tire: ['width_mm' => '205.00', 'aspect_ratio' => '55.00', 'rim_diameter_in' => '16.00'], wheel: null,
    );
}

function snapshotStorefrontProduct(string $priceObservedAt): SupplierSku
{
    $supplier = Supplier::create(['code' => 'snapshot-ndi', 'name' => 'Snapshot NDI', 'is_active' => true]);
    $brand = Brand::create(['canonical_name' => 'Nordlys', 'slug' => 'nordlys']);
    $product = Product::create([
        'product_type' => ProductType::Tire, 'brand_id' => $brand->id, 'model_name' => 'Arctic', 'title' => 'Nordlys Arctic',
        'publication_status' => PublicationStatus::Published, 'first_seen_at' => now(),
    ]);
    $product->tireSpec()->create([
        'width_mm' => '205.00', 'aspect_ratio' => '55.00', 'rim_diameter_in' => '16.00',
        'load_index' => '91', 'speed_symbol' => 'V', 'season' => 'Summer',
    ]);
    $sku = SupplierSku::create([
        'supplier_id' => $supplier->id, 'product_id' => $product->id,
        'external_sku' => 'SNAPSHOT-TIRE', 'catalog_state' => CatalogState::Catalog,
    ]);
    $artifactId = (string) Str::ulid();
    DB::table('source_artifacts')->insert([
        'id' => $artifactId, 'supplier_id' => $supplier->id, 'kind' => 'fixture',
        'original_filename' => 'snapshot.xlsx', 'storage_key' => 'fixtures/snapshot.xlsx',
        'byte_size' => 1, 'sha256' => hash('sha256', $artifactId), 'received_at' => $priceObservedAt, 'is_redacted' => true,
    ]);
    $list = PriceList::create([
        'supplier_id' => $supplier->id, 'code' => 'public', 'currency' => 'NOK',
        'tax_mode' => TaxMode::Gross, 'valid_from' => '2026-01-01 00:00:00',
    ]);
    SkuPrice::create([
        'supplier_sku_id' => $sku->id, 'price_list_id' => $list->id, 'amount' => '1000.0000',
        'observed_at' => $priceObservedAt, 'source_artifact_id' => $artifactId,
    ]);
    $warehouse = Warehouse::create(['supplier_id' => $supplier->id, 'code' => 'tromso', 'name' => 'Tromsø', 'is_active' => true]);
    CurrentStock::create([
        'supplier_sku_id' => $sku->id, 'warehouse_id' => $warehouse->id, 'quantity' => 8,
        'observed_at' => now(), 'source_artifact_id' => $artifactId,
    ]);

    return $sku;
}
