<?php

use App\Modules\Commerce\Models\ServiceAddon;
use App\Modules\Content\Models\ServicePage;
use Database\Seeders\SeoServicePageSeeder;
use Database\Seeders\ServicePageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([ServicePageSeeder::class, SeoServicePageSeeder::class]);
});

test('customer SEO service paths render structured copy and canonical metadata', function (): void {
    $paths = [
        'dekkskift-tromso',
        'dekkhotell-tromso',
        'dekkskift-tesla-elbil-tromso',
        'omlegging-balansering-dekk-tromso',
        'dekkreparasjon-tromso',
        'tpms-sensor-tromso',
        'oljeskift-tromso',
        'bilbatteri-tromso',
        'bildiagnose-tromso',
        'bytte-lyspaerer-viskerblad-tromso',
    ];

    foreach ($paths as $path) {
        $this->get('/'.$path)
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/'.$path).'">', false)
            ->assertSee('<h2', false);
    }
});

test('legacy service URLs redirect to their root canonical and sitemap publishes one version', function (): void {
    $this->get('/tjenester/mobilt-dekkskift')
        ->assertRedirect('/dekkskift-tromso')
        ->assertStatus(301);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee(url('/dekkskift-tromso'), false)
        ->assertDontSee(url('/tjenester/mobilt-dekkskift'), false);
});

test('SEO import is idempotent and preserves subsequent editor changes', function (): void {
    expect(ServicePage::query()->count())->toBe(13);

    ServicePage::query()->where('slug', 'dekkhotell')->update(['title_nb' => 'Redigert av EasyTire']);
    $this->seed(SeoServicePageSeeder::class);

    expect(ServicePage::query()->where('slug', 'dekkhotell')->value('title_nb'))->toBe('Redigert av EasyTire');
});

test('Markdown is rendered safely without allowing editor HTML', function (): void {
    ServicePage::query()->where('slug', 'dekkreparasjon')->update([
        'body_nb' => "## Trygg overskrift\n\n<script>alert('x')</script>\n\n[bad](javascript:alert(1))",
    ]);

    $this->get('/dekkreparasjon-tromso')
        ->assertOk()
        ->assertSee('<h2>Trygg overskrift</h2>', false)
        ->assertDontSee('<script>', false)
        ->assertDontSee('href="javascript:', false);
});

test('store and pricing landing pages are available in Norwegian and gated Russian QA', function (): void {
    $this->get('/dekk-og-felger')
        ->assertOk()
        ->assertSee('Dekk og felger til bilen din')
        ->assertSee('<link rel="canonical" href="'.route('storefront.store.index').'">', false);

    $this->get('/priser')->assertOk()->assertSee('Prislisten klargjøres');

    $addon = ServiceAddon::query()->firstOrFail();
    $addon->update(['price_minor' => 14900, 'is_active' => true]);
    $this->get('/priser')->assertOk()->assertSee('149,00 NOK')->assertSee('data-testid="public-price-list"', false);

    config()->set('storefront.ru_preview_enabled', true);
    $this->get('/ru/dekk-og-felger')->assertOk()->assertSee('Шины и диски для вашего автомобиля')->assertSee('noindex,nofollow', false);
    $this->get('/ru/dekkskift-tromso')->assertOk()->assertSee('Замена колёс в Тромсё')->assertSee(route('storefront.seo-services.show', 'dekkskift-tromso'), false);
    $this->get('/ru/priser')->assertOk()->assertSee('Понятные цены без догадок')->assertSee('noindex,nofollow', false);
});
