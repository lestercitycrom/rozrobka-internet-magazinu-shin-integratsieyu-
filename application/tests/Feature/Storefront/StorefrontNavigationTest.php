<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('vehicle lookup is discoverable from the Norwegian public navigation and homepage', function (): void {
    $response = $this->get('/');

    $response
        ->assertOk()
        ->assertSee('href="'.route('storefront.fitment.create').'"', false)
        ->assertSee(__('fitment.nav'))
        ->assertSee(__('fitment.home_cta'));
});

test('vehicle lookup is discoverable in the private Russian preview', function (): void {
    config()->set('storefront.ru_preview_enabled', true);

    $this->get('/ru')
        ->assertOk()
        ->assertSee('href="'.route('storefront.preview.fitment.create').'"', false)
        ->assertSee(__('fitment.nav'));
});
