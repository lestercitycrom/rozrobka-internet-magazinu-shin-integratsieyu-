<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('cart rejects malformed SKU and honeypot checkout submission', function (): void {
    $this->post('/handlekurv', ['sku' => '<script>', 'quantity' => 1])->assertSessionHasErrors('sku');
    $this->post('/kasse', ['name' => 'Test', 'email' => 'a@example.test', 'phone' => '1', 'consent' => 1, 'terms' => 1, 'website' => 'bot'])->assertSessionHasErrors('website');
});

test('unknown public order reference is not exposed', function (): void {
    $this->get('/bestilling/ET-UNKNOWN-REFERENCE')->assertNotFound();
});

test('checkout rejects an idempotency key that does not match the session', function (): void {
    $this->withSession(['checkout.key' => '11111111-1111-4111-8111-111111111111'])
        ->post('/kasse', ['idempotency_key' => '22222222-2222-4222-8222-222222222222', 'name' => 'Test', 'email' => 'a@example.test', 'phone' => '1', 'consent' => 1, 'terms' => 1])
        ->assertStatus(419);
});
