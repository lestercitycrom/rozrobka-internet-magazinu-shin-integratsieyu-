<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('vehicle lookup is discoverable through the Norwegian canonical store landing', function (): void {
    $response = $this->get('/');

    $response
        ->assertOk()
        ->assertSee('href="'.route('storefront.store.index').'"', false)
        ->assertSee(__('fitment.nav'))
        ->assertSee(__('fitment.home_cta'));
});

test('vehicle lookup is discoverable through the private Russian store preview', function (): void {
    config()->set('storefront.ru_preview_enabled', true);

    $this->get('/ru')
        ->assertOk()
        ->assertSee('href="'.route('storefront.preview.store.index').'"', false)
        ->assertSee(__('fitment.nav'));
});

test('the current Norwegian storefront section is exposed in both navigation variants', function (): void {
    $response = $this->get('/felger');

    $response
        ->assertOk()
        ->assertSee('data-testid="desktop-navigation"', false)
        ->assertSee('data-testid="mobile-navigation"', false)
        ->assertSee('min-h-11', false)
        ->assertSee('aria-current="page"', false);

    expect(substr_count($response->getContent(), 'aria-current="page"'))->toBe(2);
});

test('services navigation exposes the customer requested direct paths', function (): void {
    $response = $this->get('/')->assertOk();

    $response
        ->assertSee('Sesongskift')
        ->assertSee('Hjemme hos deg')
        ->assertSee('På vårt verksted')
        ->assertSee('Dekkhotell')
        ->assertSee('Andre biltjenester')
        ->assertSee('href="'.route('storefront.seo-services.show', 'dekkskift-tromso').'"', false)
        ->assertSee('href="'.e(route('storefront.requests.create', ['slug' => 'mobilt-dekkskift', 'fulfilment' => 'mobile'])).'"', false)
        ->assertSee('href="'.e(route('storefront.requests.create', ['slug' => 'mobilt-dekkskift', 'fulfilment' => 'workshop'])).'"', false)
        ->assertSee('href="'.route('storefront.seo-services.show', 'dekkhotell-tromso').'"', false)
        ->assertSee('href="'.route('storefront.services.index').'#additional-services"', false);
});

test('the current Russian preview section uses the same accessible navigation state', function (): void {
    config()->set('storefront.ru_preview_enabled', true);

    $response = $this->get('/ru/tjenester');

    $response
        ->assertOk()
        ->assertSee('href="'.route('storefront.preview.services.index').'"', false)
        ->assertSee('aria-current="page"', false);

    expect(substr_count($response->getContent(), 'aria-current="page"'))->toBe(2);
});
