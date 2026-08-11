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

test('the current Russian preview section uses the same accessible navigation state', function (): void {
    config()->set('storefront.ru_preview_enabled', true);

    $response = $this->get('/ru/tjenester');

    $response
        ->assertOk()
        ->assertSee('href="'.route('storefront.preview.services.index').'"', false)
        ->assertSee('aria-current="page"', false);

    expect(substr_count($response->getContent(), 'aria-current="page"'))->toBe(2);
});
