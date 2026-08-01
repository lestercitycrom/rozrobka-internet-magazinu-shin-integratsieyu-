<?php

use App\Modules\Catalog\ReadModel\CatalogReadService;
use App\Modules\Content\Models\MediaAsset;
use App\Modules\Content\Models\MediaAssignment;
use App\Modules\Orders\Models\Order;
use App\Modules\Pricing\SellingPriceService;
use Database\Seeders\E2eStorefrontSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('the restricted E2E seed is repeatable and provides a priced buyer and admin fixture', function (): void {
    $this->seed(E2eStorefrontSeeder::class);

    $orderId = Order::query()->where('public_reference', 'ET-E2E-ORDER')->value('id');
    $this->seed(E2eStorefrontSeeder::class);

    $order = Order::query()->with('items')->where('public_reference', 'ET-E2E-ORDER')->sole();
    $item = app(CatalogReadService::class)->findPublicSku('000205550016');
    $wheel = app(CatalogReadService::class)->findPublicSku('DEMO-WHEEL-18112');
    $sellingPrice = app(SellingPriceService::class)->forItem($item);
    $wheelSellingPrice = app(SellingPriceService::class)->forItem($wheel);
    $asset = MediaAsset::query()->where('path', 'media/e2e-demo-product.png')->sole();
    $dimensions = getimagesize(Storage::disk($asset->disk)->path($asset->path));

    expect($order->id)->toBe($orderId)
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->sole()->product_snapshot)->toMatchArray(['type' => 'tire'])
        ->and($item)->not->toBeNull()
        ->and($sellingPrice)->not->toBeNull()
        ->and($sellingPrice->money->minor)->toBe(149900)
        ->and($wheel)->not->toBeNull()
        ->and($wheel->wheel)->toMatchArray(['bolt_holes' => 5, 'pcd_mm' => '112.00'])
        ->and($wheelSellingPrice)->not->toBeNull()
        ->and($wheelSellingPrice->money->minor)->toBe(219900)
        ->and(Storage::disk($asset->disk)->exists($asset->path))->toBeTrue()
        ->and($dimensions)->not->toBeFalse()
        ->and($dimensions[0])->toBeGreaterThan(0)
        ->and($dimensions[1])->toBeGreaterThan(0)
        ->and(MediaAssignment::query()->where('media_asset_id', $asset->id)->where('role', 'primary')->exists())->toBeTrue();
});
