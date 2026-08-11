<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the primary storefront uses the approved sharp visual style', function (): void {
    config()->set('app.url', 'https://staging-easytire.bethub.fun');

    $this->get('https://staging-easytire.bethub.fun/')
        ->assertOk()
        ->assertSee('data-storefront-style="sharp"', false)
        ->assertDontSee('<meta name="robots" content="noindex,nofollow">', false)
        ->assertSee('<link rel="canonical" href="https://staging-easytire.bethub.fun">', false);
});

test('the Russian QA route stays private while using the approved style', function (): void {
    config()->set('storefront.ru_preview_enabled', true);

    $this->get('/ru')
        ->assertOk()
        ->assertSee('data-storefront-style="sharp"', false)
        ->assertSee('<meta name="robots" content="noindex,nofollow">', false);
});
