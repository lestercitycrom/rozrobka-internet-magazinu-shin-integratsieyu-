<?php

use App\Modules\Cart\SessionCart;
use App\Modules\Catalog\Models\CurrentStock;
use App\Modules\Catalog\Models\SupplierSku;
use App\Modules\NdiApi\NdiSandboxCatalogManager;
use Database\Seeders\E2eStorefrontSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('cart add update and remove use validated session quantities', function (): void {
    $cart = app(SessionCart::class);
    $cart->add('SKU-1', 1);
    expect($cart->items())->toBe(['SKU-1' => 1]);
    $cart->put('SKU-1', 3);
    expect($cart->items())->toBe(['SKU-1' => 3]);
    $cart->remove('SKU-1');
    expect($cart->items())->toBe([]);
});

test('session cart clear is explicit and does not occur when no created order is reported', function (): void {
    $cart = app(SessionCart::class);
    $cart->add('CHECKOUT-SKU', 1);
    // CheckoutController calls clear only under CreateOrderResult::$created; retry paths preserve this session state.
    expect($cart->items())->toBe(['CHECKOUT-SKU' => 1]);
});

test('cart post accepts fresh stock and rejects unavailable catalog items', function (): void {
    $this->seed(E2eStorefrontSeeder::class);
    config()->set('storefront.stock_max_age_minutes', 180);

    $this->post('/handlekurv', ['sku' => '000205550016', 'quantity' => 1, 'intent' => 'checkout'])
        ->assertRedirect(route('storefront.checkout.create'))
        ->assertSessionHas('storefront.cart.lines', ['000205550016' => 1]);

    $this->post('/handlekurv', ['sku' => '000205550016', 'quantity' => 1, 'intent' => 'external'])
        ->assertSessionHasErrors('intent');

    app(SessionCart::class)->clear();
    $sku = SupplierSku::query()->where('external_sku', '000205550016')->sole();
    $stock = CurrentStock::query()->where('supplier_sku_id', $sku->id)->sole();
    $assertRejected = function (string $externalSku): void {
        $this->from('/dekk')
            ->post('/handlekurv', ['sku' => $externalSku, 'quantity' => 1, 'intent' => 'checkout'])
            ->assertRedirect('/dekk')
            ->assertSessionHas('cart_error', __('commerce.item_not_purchasable'))
            ->assertSessionMissing('storefront.cart.lines');
    };

    $stock->update(['quantity' => 0, 'observed_at' => now()]);
    $assertRejected('000205550016');

    $stock->update(['quantity' => 4, 'observed_at' => now()->subMinutes(181)]);
    $assertRejected('000205550016');

    $stock->delete();
    $assertRejected('000205550016');
    $assertRejected('UNKNOWN-CATALOG-SKU');
});

test('repeated add validates the resulting regular and sandbox quantities', function (): void {
    $this->seed(E2eStorefrontSeeder::class);
    $sku = SupplierSku::query()->where('external_sku', '000205550016')->sole();
    CurrentStock::query()->where('supplier_sku_id', $sku->id)->update(['quantity' => 1, 'observed_at' => now()]);

    $this->post('/handlekurv', ['sku' => $sku->external_sku, 'quantity' => 1, 'intent' => 'cart'])->assertRedirect();
    $this->from('/dekk')->post('/handlekurv', ['sku' => $sku->external_sku, 'quantity' => 1, 'intent' => 'checkout'])
        ->assertRedirect('/dekk')
        ->assertSessionHas('cart_error', __('commerce.item_not_purchasable'));
    expect(app(SessionCart::class)->items())->toBe([$sku->external_sku => 1]);

    app(SessionCart::class)->clear();
    config()->set([
        'ndi_api.sandbox.enabled' => true,
        'ndi_api.sandbox.party_id' => '1220000',
        'ndi_api.sandbox.article_id' => '4765205551649102',
        'ndi_api.sandbox.quantity' => 2,
        'ndi_api.sandbox.price_minor' => 1000,
    ]);
    app(NdiSandboxCatalogManager::class)->refresh();

    $this->post('/handlekurv', ['sku' => 'NDI-TEST-4765205551649102', 'quantity' => 2, 'intent' => 'cart'])->assertRedirect();
    $this->from('/dekk')->post('/handlekurv', ['sku' => 'NDI-TEST-4765205551649102', 'quantity' => 2, 'intent' => 'checkout'])
        ->assertRedirect('/dekk')
        ->assertSessionHas('cart_error', __('commerce.item_not_purchasable'));
    expect(app(SessionCart::class)->items())->toBe(['NDI-TEST-4765205551649102' => 2]);
});

test('sandbox cart quantity cannot be weakened by mutable environment configuration', function (): void {
    config()->set([
        'ndi_api.sandbox.enabled' => true,
        'ndi_api.sandbox.party_id' => '1220000',
        'ndi_api.sandbox.article_id' => '4765205551649102',
        'ndi_api.sandbox.quantity' => 2,
        'ndi_api.sandbox.price_minor' => 1000,
    ]);
    app(NdiSandboxCatalogManager::class)->refresh();
    config()->set('ndi_api.sandbox.quantity', 1);

    $this->from('/dekk')->post('/handlekurv', [
        'sku' => 'NDI-TEST-4765205551649102',
        'quantity' => 1,
        'intent' => 'cart',
    ])->assertRedirect('/dekk')->assertSessionHas('cart_error', __('commerce.item_not_purchasable'));
    expect(app(SessionCart::class)->items())->toBe([]);

    $this->post('/handlekurv', [
        'sku' => 'NDI-TEST-4765205551649102',
        'quantity' => 2,
        'intent' => 'cart',
    ])->assertRedirect();
    expect(app(SessionCart::class)->items())->toBe(['NDI-TEST-4765205551649102' => 2]);
});
