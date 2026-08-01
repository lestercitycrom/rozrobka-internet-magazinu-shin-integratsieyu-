<?php

use App\Modules\Cart\CartResolver;
use App\Modules\Cart\Data\CartLine;
use App\Modules\Cart\Data\CartServiceLine;
use App\Modules\Cart\Data\CartSnapshot;
use App\Modules\Cart\SessionCart;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\ReadModel\CatalogItem;
use App\Modules\Catalog\ReadModel\CatalogStock;
use App\Modules\Commerce\Enums\ServiceAddonApplicability;
use App\Modules\Commerce\Enums\ServiceAddonBillingMode;
use App\Modules\Commerce\Models\ServiceAddon;
use App\Modules\Orders\CreatePendingOrder;
use App\Modules\Orders\Data\CheckoutData;
use App\Modules\Orders\Enums\FulfilmentMethod;
use App\Modules\Pricing\Data\SellingPrice;
use App\Modules\Pricing\ValueObjects\Money;
use Database\Seeders\E2eStorefrontSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('checkout freezes services separately while NDI product items stay product only', function (): void {
    $this->seed(E2eStorefrontSeeder::class);
    $addon = ServiceAddon::create([
        'code' => 'order-snapshot-care',
        'title_nb' => 'Ordretjeneste',
        'title_ru' => 'Услуга заказа',
        'applicability' => ServiceAddonApplicability::Tire,
        'billing_mode' => ServiceAddonBillingMode::PerItem,
        'currency' => 'NOK',
        'price_minor' => 1750,
        'is_active' => true,
        'sort_order' => 50,
    ]);
    $cart = app(SessionCart::class);
    $cart->add('000205550016', 2);
    $cart->addServiceAddon($addon->code);

    $result = app(CreatePendingOrder::class)->execute(addonCheckoutData(), app(CartResolver::class)->resolve());
    $order = $result->order->load(['items', 'serviceItems']);
    $serviceItem = $order->serviceItems->sole();

    expect($order->items)->toHaveCount(1)
        ->and($order->items->sole()->external_sku)->toBe('000205550016')
        ->and($serviceItem->service_code)->toBe('order-snapshot-care')
        ->and($serviceItem->quantity)->toBe(2)
        ->and($serviceItem->unit_price_minor)->toBe(1750)
        ->and($serviceItem->line_total_minor)->toBe(3500)
        ->and($serviceItem->service_snapshot)->toMatchArray([
            'title_nb' => 'Ordretjeneste',
            'title_ru' => 'Услуга заказа',
            'billing_mode' => 'per_item',
            'quantity' => 2,
            'line_total_minor' => 3500,
        ])
        ->and($order->subtotal_minor)->toBe(303300)
        ->and($order->total_minor)->toBe(303300);

    $addon->delete();
    $serviceItem->refresh();
    expect($serviceItem->service_addon_id)->toBeNull()
        ->and($serviceItem->service_snapshot['title_nb'])->toBe('Ordretjeneste');
});

test('a positive service cannot make a zero priced product cart checkout ready', function (): void {
    $zero = new Money(0, 'NOK');
    $item = new CatalogItem(
        sku: 'ZERO-SKU',
        productType: ProductType::Tire,
        brand: 'Fixture',
        modelName: 'Zero',
        title: 'Zero product',
        prices: [],
        stock: new CatalogStock(2, now()->toISOString()),
        tire: ['width_mm' => '205.00'],
        wheel: null,
    );
    $sellingPrice = new SellingPrice($zero, $zero, 1, 'fixture', 'public', now()->toISOString());
    $service = new CartServiceLine(
        serviceAddonId: 1,
        code: 'positive-service',
        titleNb: 'Tjeneste',
        titleRu: 'Услуга',
        applicability: ServiceAddonApplicability::All,
        billingMode: ServiceAddonBillingMode::PerOrder,
        unitPrice: new Money(1000, 'NOK'),
        quantity: 1,
    );
    $snapshot = new CartSnapshot(
        lines: [new CartLine($item, $sellingPrice, 1)],
        issues: [],
        total: new Money(1000, 'NOK'),
        serviceLines: [$service],
    );

    expect($snapshot->isCheckoutReady())->toBeFalse();
});

function addonCheckoutData(): CheckoutData
{
    return new CheckoutData(
        idempotencyKey: (string) Str::uuid(),
        locale: 'nb',
        customerName: 'Service Buyer',
        customerEmail: 'service-buyer@example.test',
        customerPhone: '+47 900 00 001',
        fulfilmentMethod: FulfilmentMethod::Pickup,
        vehicleRegistration: null,
        customerNote: null,
    );
}
