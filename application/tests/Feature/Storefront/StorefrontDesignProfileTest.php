<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Vite as ViteFacade;
use Illuminate\Support\HtmlString;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    ViteFacade::clearResolvedInstance();
    $this->swap(Vite::class, new class extends Vite
    {
        public function __invoke($entrypoints, $buildDirectory = null): HtmlString
        {
            $tags = collect((array) $entrypoints)
                ->map(fn (string $entrypoint): string => '<meta name="vite-entry" content="'.e($entrypoint).'">')
                ->implode('');

            return new HtmlString($tags);
        }

        public function asset($asset, $buildDirectory = null): string
        {
            return '/build/testing/'.basename((string) $asset);
        }
    });
});

test('modern is the default profile and keeps the normal NB asset branch', function (): void {
    config()->set('app.url', 'https://staging-easytire.bethub.fun');

    expect(config('storefront.design_profile'))->toBe('modern');

    $this->get('https://staging-easytire.bethub.fun/priser')
        ->assertOk()
        ->assertSee('data-storefront-style="sharp"', false)
        ->assertSee('data-storefront-design="modern"', false)
        ->assertSee('<meta name="vite-entry" content="resources/css/app.css">', false)
        ->assertSee('<meta name="vite-entry" content="resources/js/app.js">', false)
        ->assertDontSee('resources/css/storefront-classic.css', false)
        ->assertDontSee('<meta name="robots" content="noindex,nofollow">', false)
        ->assertSee('<link rel="canonical" href="https://staging-easytire.bethub.fun/priser">', false);
});

test('an invalid configured profile fails closed to modern', function (): void {
    config()->set('storefront.design_profile', 'classic-preview-from-query');

    $this->get('/')
        ->assertOk()
        ->assertSee('data-storefront-design="modern"', false)
        ->assertSee('<meta name="vite-entry" content="resources/css/app.css">', false)
        ->assertSee('<meta name="vite-entry" content="resources/js/app.js">', false)
        ->assertDontSee('resources/css/storefront-classic.css', false)
        ->assertDontSee('<meta name="robots" content="noindex,nofollow">', false);
});

test('classic NB uses the override entry and primary canonical while navigation stays on its host', function (): void {
    config()->set('storefront.design_profile', 'classic');
    config()->set('storefront.canonical_origin', 'https://staging-easytire.bethub.fun');

    $this->get('https://staging-classic-easytire.bethub.fun/priser')
        ->assertOk()
        ->assertSee('data-storefront-style="sharp"', false)
        ->assertSee('data-storefront-design="classic"', false)
        ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
        ->assertSee('<link rel="canonical" href="https://staging-easytire.bethub.fun/priser">', false)
        ->assertSee('<meta property="og:url" content="https://staging-easytire.bethub.fun/priser">', false)
        ->assertSee('href="https://staging-classic-easytire.bethub.fun/priser"', false)
        ->assertSee('<meta name="vite-entry" content="resources/css/app.css">', false)
        ->assertSee('<meta name="vite-entry" content="resources/css/storefront-classic.css">', false)
        ->assertSee('<meta name="vite-entry" content="resources/js/app.js">', false);
});

test('classic RU keeps localized routes on its host and canonicalizes the NB path', function (): void {
    config()->set('storefront.design_profile', 'classic');
    config()->set('storefront.canonical_origin', 'https://staging-easytire.bethub.fun');
    config()->set('storefront.ru_preview_enabled', true);

    $this->get('https://staging-classic-easytire.bethub.fun/ru/priser')
        ->assertOk()
        ->assertSee('lang="ru"', false)
        ->assertSee('data-storefront-design="classic"', false)
        ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
        ->assertSee('<link rel="canonical" href="https://staging-easytire.bethub.fun/priser">', false)
        ->assertSee('href="https://staging-classic-easytire.bethub.fun/ru/priser"', false)
        ->assertSee('<meta name="vite-entry" content="resources/css/storefront-classic.css">', false)
        ->assertSee('<meta name="vite-entry" content="resources/js/app.js">', false);
});

test('modern RU keeps its existing privacy and asset behavior', function (): void {
    config()->set('storefront.ru_preview_enabled', true);

    $this->get('/ru')
        ->assertOk()
        ->assertSee('data-storefront-design="modern"', false)
        ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
        ->assertSee('<meta name="vite-entry" content="resources/css/app.css">', false)
        ->assertSee('<meta name="vite-entry" content="resources/js/app.js">', false)
        ->assertDontSee('resources/css/storefront-classic.css', false);
});

test('classic storefront configuration does not affect the admin layout', function (): void {
    config()->set('storefront.design_profile', 'classic');

    $this->get('/admin/login')
        ->assertOk()
        ->assertDontSee('data-storefront-design=', false)
        ->assertDontSee('resources/css/storefront-classic.css', false)
        ->assertSee('<meta name="vite-entry" content="resources/css/app.css">', false)
        ->assertSee('<meta name="vite-entry" content="resources/js/app.js">', false);
});
