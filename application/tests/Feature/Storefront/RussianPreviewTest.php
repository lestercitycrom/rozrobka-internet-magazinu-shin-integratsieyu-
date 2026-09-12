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
    $russianHeroTitle = __('pro.home.hero_title', [], 'ru');
    $norwegianHeroTitle = __('pro.home.hero_title', [], 'nb');

    $this->get('/ru')->assertOk()->assertSee($russianHeroTitle)->assertSee('noindex,nofollow', false)->assertSee('rel="canonical"', false)->assertSee('lang="ru"', false);
    $this->get('/')->assertOk()->assertSee($norwegianHeroTitle)->assertDontSee($russianHeroTitle);
});

test('russian preview 4xx is localized and preserves preview SEO guardrails', function (): void {
    config()->set('storefront.ru_preview_enabled', true);

    $this->get('/ru/tjenester/does-not-exist')
        ->assertNotFound()
        ->assertSee(__('errors.heading', [], 'ru'))
        ->assertDontSee(__('errors.heading', [], 'nb'))
        ->assertSee('lang="ru"', false)
        ->assertSee('noindex,nofollow', false)
        ->assertSee('rel="canonical"', false)
        ->assertSee('/tjenester/does-not-exist', false);
});
