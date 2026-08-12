<?php

use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Enums\TaxMode;
use App\Modules\Catalog\ReadModel\CatalogItem;
use App\Modules\Catalog\ReadModel\CatalogPrice;
use App\Modules\Catalog\ReadModel\CatalogStock;
use App\Modules\Pricing\Enums\PricingRuleScope;
use App\Modules\Pricing\Models\PricingRule;
use App\Modules\Pricing\SellingPriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('selling price requires an active explicit rule and matching source list', function (): void {
    $item = pricingCatalogItem();
    $service = app(SellingPriceService::class);

    expect($service->forItem($item))->toBeNull();

    $rule = PricingRule::create([
        'name' => 'Confirmed retail rule',
        'scope' => PricingRuleScope::Global,
        'source_price_list_code' => 'public',
        'currency' => 'NOK',
        'markup_basis_points' => 1000,
        'fixed_markup_minor' => 500,
        'rounding_increment_minor' => 100,
        'priority' => 1,
        'is_active' => true,
    ]);

    $price = $service->forItem($item);
    expect($rule->fresh()->source_tax_mode)->toBe(TaxMode::Gross)
        ->and($price)->not->toBeNull()
        ->and($price->sourceMoney->minor)->toBe(100000)
        ->and($price->money->minor)->toBe(110500)
        ->and($price->sourcePriceListCode)->toBe('public');
});

test('gross and net rules select only their explicit source from the same price list', function (): void {
    $item = new CatalogItem(
        sku: 'PRICE-SKU',
        productType: ProductType::Tire,
        brand: 'Nordlys',
        modelName: 'Arctic',
        title: 'Nordlys Arctic',
        prices: [
            new CatalogPrice('1200.0000', 'NOK', TaxMode::Gross, now()->toISOString(), 'public'),
            new CatalogPrice('800.0000', 'NOK', TaxMode::Net, now()->toISOString(), 'public'),
        ],
        stock: new CatalogStock(8, now()->toISOString()),
        tire: ['width_mm' => '205.00', 'aspect_ratio' => '55.00', 'rim_diameter_in' => '16.00'],
        wheel: null,
    );
    $rule = PricingRule::create([
        'name' => 'Explicit source mode',
        'scope' => PricingRuleScope::Global,
        'source_price_list_code' => 'public',
        'source_tax_mode' => TaxMode::Gross,
        'currency' => 'NOK',
        'markup_basis_points' => 1000,
        'fixed_markup_minor' => 0,
        'rounding_increment_minor' => 1,
        'priority' => 1,
        'is_active' => true,
    ]);

    $gross = app(SellingPriceService::class)->forItem($item);
    $rule->update(['source_tax_mode' => TaxMode::Net]);
    $net = app(SellingPriceService::class)->forItem($item);

    expect($gross?->sourceMoney->minor)->toBe(120000)
        ->and($gross?->money->minor)->toBe(132000)
        ->and($net?->sourceMoney->minor)->toBe(80000)
        ->and($net?->money->minor)->toBe(88000);
});

test('gross is preferred and net is used only as an explicit fallback', function (): void {
    foreach ([
        ['name' => 'Recommended Gross', 'source_tax_mode' => TaxMode::Gross, 'markup_basis_points' => 0],
        ['name' => 'Net fallback', 'source_tax_mode' => TaxMode::Net, 'markup_basis_points' => 2500],
    ] as $rule) {
        PricingRule::create([
            ...$rule,
            'scope' => PricingRuleScope::Global,
            'source_price_list_code' => 'public',
            'currency' => 'NOK',
            'fixed_markup_minor' => 0,
            'rounding_increment_minor' => 100,
            'priority' => 100,
            'is_active' => true,
        ]);
    }

    $item = pricingCatalogItem();
    $withBoth = new CatalogItem(
        sku: $item->sku, productType: $item->productType, brand: $item->brand,
        modelName: $item->modelName, title: $item->title,
        prices: [
            new CatalogPrice('1200.0000', 'NOK', TaxMode::Gross, now()->toISOString(), 'public'),
            new CatalogPrice('800.0000', 'NOK', TaxMode::Net, now()->toISOString(), 'public'),
        ],
        stock: $item->stock, tire: $item->tire, wheel: null,
    );
    $netOnly = new CatalogItem(
        sku: $item->sku, productType: $item->productType, brand: $item->brand,
        modelName: $item->modelName, title: $item->title,
        prices: [new CatalogPrice('800.0000', 'NOK', TaxMode::Net, now()->toISOString(), 'public')],
        stock: $item->stock, tire: $item->tire, wheel: null,
    );

    $gross = app(SellingPriceService::class)->forItem($withBoth);
    $fallback = app(SellingPriceService::class)->forItem($netOnly);

    expect($gross?->ruleName)->toBe('Recommended Gross')
        ->and($gross?->money->minor)->toBe(120000)
        ->and($fallback?->ruleName)->toBe('Net fallback')
        ->and($fallback?->money->minor)->toBe(100000);
});

test('specific pricing rule wins when priorities are equal', function (): void {
    $item = pricingCatalogItem();
    foreach ([
        [PricingRuleScope::Global, null, 500],
        [PricingRuleScope::Brand, 'Nordlys', 1000],
        [PricingRuleScope::Sku, 'PRICE-SKU', 1500],
    ] as [$scope, $key, $basisPoints]) {
        PricingRule::create([
            'name' => $scope->value,
            'scope' => $scope,
            'scope_key' => $key,
            'source_price_list_code' => 'public',
            'currency' => 'NOK',
            'markup_basis_points' => $basisPoints,
            'fixed_markup_minor' => 0,
            'rounding_increment_minor' => 1,
            'priority' => 5,
            'is_active' => true,
        ]);
    }

    expect(app(SellingPriceService::class)->forItem($item)?->money->minor)->toBe(115000);
});

test('batch pricing loads the active rule set only once', function (): void {
    PricingRule::create([
        'name' => 'Batch retail',
        'scope' => PricingRuleScope::Global,
        'source_price_list_code' => 'public',
        'currency' => 'NOK',
        'markup_basis_points' => 1000,
        'fixed_markup_minor' => 0,
        'rounding_increment_minor' => 100,
        'priority' => 1,
        'is_active' => true,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $prices = app(SellingPriceService::class)->forItems([pricingCatalogItem(), pricingCatalogItem()]);
    $ruleQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'pricing_rules'));
    DB::disableQueryLog();

    expect($prices)->toHaveCount(2)
        ->and($prices[0]?->money->minor)->toBe(110000)
        ->and($prices[1]?->money->minor)->toBe(110000)
        ->and($ruleQueries)->toHaveCount(1);
});

test('stale or malformed source prices fail closed', function (): void {
    PricingRule::create([
        'name' => 'Freshness rule', 'scope' => PricingRuleScope::Global,
        'source_price_list_code' => 'public', 'currency' => 'NOK',
        'markup_basis_points' => 0, 'fixed_markup_minor' => 0,
        'rounding_increment_minor' => 1, 'priority' => 1, 'is_active' => true,
    ]);
    config()->set('storefront.price_max_age_minutes', 60);
    $fresh = pricingCatalogItem();
    $stale = new CatalogItem(
        sku: $fresh->sku, productType: $fresh->productType, brand: $fresh->brand,
        modelName: $fresh->modelName, title: $fresh->title,
        prices: [new CatalogPrice('1000.0000', 'NOK', TaxMode::Gross, now()->subMinutes(61)->toISOString(), 'public')],
        stock: $fresh->stock, tire: $fresh->tire, wheel: null,
    );
    $missingTimestamp = new CatalogItem(
        sku: $fresh->sku, productType: $fresh->productType, brand: $fresh->brand,
        modelName: $fresh->modelName, title: $fresh->title,
        prices: [new CatalogPrice('1000.0000', 'NOK', TaxMode::Gross, '', 'public')],
        stock: $fresh->stock, tire: $fresh->tire, wheel: null,
    );
    $futureTimestamp = new CatalogItem(
        sku: $fresh->sku, productType: $fresh->productType, brand: $fresh->brand,
        modelName: $fresh->modelName, title: $fresh->title,
        prices: [new CatalogPrice('1000.0000', 'NOK', TaxMode::Gross, now()->addMinutes(6)->toISOString(), 'public')],
        stock: $fresh->stock, tire: $fresh->tire, wheel: null,
    );

    expect(app(SellingPriceService::class)->forItem($fresh))->not->toBeNull()
        ->and(app(SellingPriceService::class)->forItem($stale))->toBeNull()
        ->and(app(SellingPriceService::class)->forItem($missingTimestamp))->toBeNull()
        ->and(app(SellingPriceService::class)->forItem($futureTimestamp))->toBeNull();
});

test('invalid stored adjustments fail closed instead of exposing zero price or crashing the catalog', function (int $basisPoints, int $fixedMinor, int $roundingMinor): void {
    PricingRule::create([
        'name' => 'Unsafe imported rule',
        'scope' => PricingRuleScope::Global,
        'source_price_list_code' => 'public',
        'currency' => 'NOK',
        'markup_basis_points' => $basisPoints,
        'fixed_markup_minor' => $fixedMinor,
        'rounding_increment_minor' => $roundingMinor,
        'priority' => 1,
        'is_active' => true,
    ]);

    expect(app(SellingPriceService::class)->forItem(pricingCatalogItem()))->toBeNull();
})->with([
    'zero result' => [-10000, 0, 1],
    'negative result' => [-20000, 0, 1],
    'invalid rounding' => [0, 0, 0],
]);

function pricingCatalogItem(): CatalogItem
{
    return new CatalogItem(
        sku: 'PRICE-SKU',
        productType: ProductType::Tire,
        brand: 'Nordlys',
        modelName: 'Arctic',
        title: 'Nordlys Arctic',
        prices: [new CatalogPrice('1000.0000', 'NOK', TaxMode::Gross, now()->toISOString(), 'public')],
        stock: new CatalogStock(8, now()->toISOString()),
        tire: ['width_mm' => '205.00', 'aspect_ratio' => '55.00', 'rim_diameter_in' => '16.00'],
        wheel: null,
    );
}
