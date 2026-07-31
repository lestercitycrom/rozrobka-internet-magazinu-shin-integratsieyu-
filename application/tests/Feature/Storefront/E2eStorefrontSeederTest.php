<?php

use App\Modules\Catalog\ReadModel\CatalogReadService;
use App\Modules\Orders\Models\Order;
use App\Modules\Pricing\SellingPriceService;
use Database\Seeders\E2eStorefrontSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the restricted E2E seed is repeatable and provides a priced buyer and admin fixture', function (): void {
    $this->seed(E2eStorefrontSeeder::class);

    $orderId = Order::query()->where('public_reference', 'ET-E2E-ORDER')->value('id');
    $this->seed(E2eStorefrontSeeder::class);

    $order = Order::query()->with('items')->where('public_reference', 'ET-E2E-ORDER')->sole();
    $item = app(CatalogReadService::class)->findPublicSku('000205550016');
    $sellingPrice = app(SellingPriceService::class)->forItem($item);

    expect($order->id)->toBe($orderId)
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->sole()->product_snapshot)->toMatchArray(['type' => 'tire'])
        ->and($item)->not->toBeNull()
        ->and($sellingPrice)->not->toBeNull()
        ->and($sellingPrice->money->minor)->toBe(149900);
});
