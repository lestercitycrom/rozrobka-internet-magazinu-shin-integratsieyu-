<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('russian preview is hidden unless explicitly enabled', function (): void {
    config()->set('storefront.ru_preview_enabled', false);
    $this->get('/ru')->assertNotFound();
});

test('russian preview cannot be enabled in production', function (): void {
    config()->set('storefront.ru_preview_enabled', true);
    $this->app['env'] = 'production';

    $this->get('/ru')->assertNotFound();
});

test('enabled russian preview is translated and marked noindex', function (): void {
    config()->set('storefront.ru_preview_enabled', true);
    $this->get('/ru')->assertOk()->assertSee('Шины и диски для вашего ритма жизни')->assertSee('noindex,nofollow', false)->assertSee('rel="canonical"', false)->assertSee('lang="ru"', false);
    $this->get('/')->assertOk()->assertSee('Dekk og felg som passer hverdagen din')->assertDontSee('Шины и диски для вашего ритма жизни');
});
