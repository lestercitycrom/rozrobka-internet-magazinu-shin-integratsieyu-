<?php

use App\Support\StorefrontVisualStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

test('the primary storefront keeps the soft visual style', function (): void {
    config()->set('app.url', 'https://staging-easytire.bethub.fun');
    config()->set('storefront.sharp_host', 'staging-sharp-easytire.bethub.fun');

    $this->get('https://staging-easytire.bethub.fun/')
        ->assertOk()
        ->assertSee('data-storefront-style="soft"', false)
        ->assertDontSee('<meta name="robots" content="noindex,nofollow">', false)
        ->assertSee('<link rel="canonical" href="https://staging-easytire.bethub.fun">', false);
});

test('the comparison host renders sharp geometry without creating duplicate content', function (): void {
    config()->set('app.url', 'https://staging-easytire.bethub.fun');
    config()->set('storefront.sharp_host', 'staging-sharp-easytire.bethub.fun');

    $this->get('https://staging-sharp-easytire.bethub.fun/dekk')
        ->assertOk()
        ->assertSee('data-storefront-style="sharp"', false)
        ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
        ->assertSee('<link rel="canonical" href="https://staging-easytire.bethub.fun/dekk">', false)
        ->assertSee('href="https://staging-sharp-easytire.bethub.fun/dekk-og-felger"', false);
});

test('the sharp style resolver requires an exact configured host', function (): void {
    config()->set('app.url', 'https://staging-easytire.bethub.fun');
    config()->set('storefront.sharp_host', 'staging-sharp-easytire.bethub.fun');

    $sharp = new StorefrontVisualStyle(Request::create('https://staging-sharp-easytire.bethub.fun/handlekurv'));
    $lookalike = new StorefrontVisualStyle(Request::create('https://staging-sharp-easytire.bethub.fun.example.test/handlekurv'));

    expect($sharp->value())->toBe(StorefrontVisualStyle::SHARP)
        ->and($sharp->canonicalUrl('https://staging-sharp-easytire.bethub.fun/handlekurv?ignored=1'))
        ->toBe('https://staging-easytire.bethub.fun/handlekurv')
        ->and($lookalike->value())->toBe(StorefrontVisualStyle::SOFT);
});
