<?php

use App\Modules\Cart\Data\CartLine;
use App\Modules\Cart\Data\CartSnapshot;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\ReadModel\CatalogItem;
use App\Modules\Catalog\ReadModel\CatalogStock;
use App\Modules\Pricing\Data\SellingPrice;
use App\Modules\Pricing\ValueObjects\Money;

test('checkout readiness requires a strictly positive selling price and total', function (): void {
    $zero = new Money(0, 'NOK');
    $item = new CatalogItem(
        sku: 'ZERO-PRICE',
        productType: ProductType::Tire,
        brand: 'Fixture',
        modelName: 'Zero',
        title: 'Zero price fixture',
        prices: [],
        stock: new CatalogStock(4, null),
        tire: null,
        wheel: null,
    );
    $price = new SellingPrice($zero, new Money(10000, 'NOK'), 1, 'unsafe', 'public', null);
    $snapshot = new CartSnapshot([new CartLine($item, $price, 1)], [], $zero);

    expect($snapshot->isCheckoutReady())->toBeFalse();
});
