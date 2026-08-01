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

    PricingRule::create([
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
    expect($price)->not->toBeNull()
        ->and($price->sourceMoney->minor)->toBe(100000)
        ->and($price->money->minor)->toBe(110500)
        ->and($price->sourcePriceListCode)->toBe('public');
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
