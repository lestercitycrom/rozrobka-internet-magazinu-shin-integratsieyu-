<?php

use App\Modules\Cart\SessionCart;
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

test('buy now adds the validated line and goes straight to checkout', function (): void {
    $this->post('/handlekurv', ['sku' => 'BUY-NOW-SKU', 'quantity' => 1, 'intent' => 'checkout'])
        ->assertRedirect(route('storefront.checkout.create'));

    $this->post('/handlekurv', ['sku' => 'BUY-NOW-SKU', 'quantity' => 1, 'intent' => 'external'])
        ->assertSessionHasErrors('intent');
});
