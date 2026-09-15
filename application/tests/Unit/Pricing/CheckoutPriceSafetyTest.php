<?php

use App\Modules\Cart\Data\CartLine;
use App\Modules\Cart\Data\CartSnapshot;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\ReadModel\CatalogItem;
use App\Modules\Catalog\ReadModel\CatalogStock;
use App\Modules\Pricing\Data\SellingPrice;
use App\Modules\Pricing\ValueObjects\Money;
use Tests\TestCase;

uses(TestCase::class);

test('checkout readiness requires a strictly positive selling price and total', function (): void {
    $zero = new Money(0, 'NOK');
    $item = new CatalogItem(
        sku: 'ZERO-PRICE',
        productType: ProductType::Tire,
        brand: 'Fixture',
        modelName: 'Zero',
        title: 'Zero price fixture',
        prices: [],
        stock: new CatalogStock(4, now()->toISOString()),
        tire: null,
        wheel: null,
    );
    $price = new SellingPrice($zero, new Money(10000, 'NOK'), 1, 'unsafe', 'public', null);
    $snapshot = new CartSnapshot([new CartLine($item, $price, 1)], [], $zero);

    expect($snapshot->isCheckoutReady())->toBeFalse();
});

test('checkout readiness requires fresh stock sufficient for the requested quantity', function (): void {
    config()->set('storefront.stock_max_age_minutes', 180);

    $missing = checkoutSafetySnapshot(new CatalogStock(4, null));
    $stale = checkoutSafetySnapshot(new CatalogStock(4, now()->subMinutes(181)->toISOString()));
    $zero = checkoutSafetySnapshot(new CatalogStock(0, now()->toISOString()));
    $insufficient = checkoutSafetySnapshot(new CatalogStock(1, now()->toISOString()), 2);
    $ready = checkoutSafetySnapshot(new CatalogStock(2, now()->toISOString()), 2);

    expect($missing->isCheckoutReady())->toBeFalse()
        ->and($stale->isCheckoutReady())->toBeFalse()
        ->and($zero->isCheckoutReady())->toBeFalse()
        ->and($insufficient->isCheckoutReady())->toBeFalse()
        ->and($ready->isCheckoutReady())->toBeTrue();
});

function checkoutSafetySnapshot(CatalogStock $stock, int $quantity = 1): CartSnapshot
{
    $item = new CatalogItem(
        sku: 'STOCK-SAFETY',
        productType: ProductType::Tire,
        brand: 'Fixture',
        modelName: 'Stock',
        title: 'Stock safety fixture',
        prices: [],
        stock: $stock,
        tire: null,
        wheel: null,
    );
    $money = new Money(10000, 'NOK');
    $price = new SellingPrice($money, $money, 1, 'safe', 'public', now()->toISOString());

    return new CartSnapshot(
        [new CartLine($item, $price, $quantity)],
        [],
        new Money($money->minor * $quantity, $money->currency),
    );
}
