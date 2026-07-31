<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('storefront, admin layout and login expose favicon and manifest links', function (): void {
    $this->get('/')->assertOk()->assertSee('href="/favicon.svg"', false)->assertSee('href="/site.webmanifest"', false);
    $this->get('/admin/login')->assertOk()->assertSee('href="/favicon.svg"', false)->assertSee('href="/site.webmanifest"', false);
    $this->actingAs(User::factory()->operations()->create())->get('/admin')->assertOk()->assertSee('href="/favicon.svg"', false)->assertSee('href="/site.webmanifest"', false);
});

test('declared brand assets exist and manifest is valid json', function (): void {
    foreach (['favicon.svg', 'favicon.ico', 'brand/apple-touch-icon.png', 'brand/easytire-icon-32.png', 'brand/easytire-icon-192.png', 'brand/easytire-icon-512.png'] as $path) {
        expect(public_path($path))->toBeFile();
    }
    $manifest = json_decode((string) file_get_contents(public_path('site.webmanifest')), true, flags: JSON_THROW_ON_ERROR);
    expect($manifest['name'])->toBe('EasyTire')->and($manifest['icons'])->toHaveCount(2);
});
