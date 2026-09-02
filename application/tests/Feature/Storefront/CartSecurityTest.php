<?php

use Database\Seeders\E2eStorefrontSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('cart rejects malformed SKU and honeypot checkout submission', function (): void {
    $this->post('/handlekurv', ['sku' => '<script>', 'quantity' => 1])->assertSessionHasErrors('sku');
    $this->post('/kasse', ['name' => 'Test', 'email' => 'a@example.test', 'phone' => '1', 'consent' => 1, 'terms' => 1, 'website' => 'bot'])->assertSessionHasErrors('website');
});

test('unknown public order reference is not exposed', function (): void {
    $this->get('/bestilling/ET-UNKNOWN-REFERENCE')->assertNotFound();
});

test('cart exposes the styled accessible purchase controls', function (): void {
    $this->get('/handlekurv')
        ->assertOk()
        ->assertSee('data-testid="cart-empty"', false)
        ->assertSee('et-cart-empty', false)
        ->assertDontSee('border-dashed', false);
});

test('empty checkout returns safely to the cart for both get and post', function (): void {
    $key = '11111111-1111-4111-8111-111111111111';

    $this->get('/kasse')
        ->assertRedirect('/handlekurv')
        ->assertSessionHas('cart_error');

    $this->withSession(['checkout.key' => $key])
        ->post('/kasse', [
            'idempotency_key' => $key,
            'name' => 'Test',
            'email' => 'a@example.test',
            'phone' => '90000000',
            'consent' => 1,
            'terms' => 1,
        ])
        ->assertRedirect('/handlekurv')
        ->assertSessionHas('cart_error');
});

test('checkout exposes the styled accessible controls for a ready cart', function (): void {
    $this->seed(E2eStorefrontSeeder::class);
    config()->set('payments.enabled', true);

    $this->withSession(['storefront.cart.lines' => ['000205550016' => 1]])
        ->get('/kasse')
        ->assertOk()
        ->assertSee('for="checkout-name"', false)
        ->assertSee('id="checkout-email"', false)
        ->assertSee('id="checkout-phone"', false)
        ->assertSee('pattern="\+?[0-9][0-9 ()-]{7,23}"', false)
        ->assertSee(__('phone_validation.hint'))
        ->assertSee('data-testid="checkout-vat-summary"', false)
        ->assertSee('data-testid="checkout-merchant-identity"', false)
        ->assertSee('data-testid="checkout-terms"', false)
        ->assertSee('data-testid="checkout-privacy"', false)
        ->assertSee('data-testid="checkout-fitment-confirmation"', false)
        ->assertSee(__('commerce.order_with_payment_obligation'))
        ->assertSee('easytire-input', false);
});

test('checkout rejects an idempotency key that does not match the session', function (): void {
    $this->withSession(['checkout.key' => '11111111-1111-4111-8111-111111111111'])
        ->post('/kasse', ['idempotency_key' => '22222222-2222-4222-8222-222222222222', 'name' => 'Test', 'email' => 'a@example.test', 'phone' => '90000000', 'consent' => 1, 'terms' => 1])
        ->assertStatus(419);
});

test('buyer can repair a stock issue and remove the cart line', function (): void {
    $this->seed(E2eStorefrontSeeder::class);
    $session = ['storefront.cart.lines' => ['000205550016' => 5]];

    $this->withSession($session)
        ->get('/handlekurv')
        ->assertOk()
        ->assertSee('data-testid="cart-issue"', false)
        ->assertSee('max="4"', false);

    $this->patch('/handlekurv', ['sku' => '000205550016', 'quantity' => 4])
        ->assertRedirect('/handlekurv');

    $this->get('/handlekurv')
        ->assertOk()
        ->assertDontSee('data-testid="cart-issue"', false)
        ->assertSee('href="'.route('storefront.checkout.create').'"', false);

    $this->delete('/handlekurv', ['sku' => '000205550016', 'quantity' => 1])
        ->assertRedirect('/handlekurv');

    $this->get('/handlekurv')->assertOk()->assertSee(__('commerce.empty'));
});
