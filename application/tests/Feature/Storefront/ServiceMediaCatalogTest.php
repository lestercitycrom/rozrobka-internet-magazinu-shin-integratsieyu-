<?php

use App\Modules\Content\Enums\ServicePageLocale;
use App\Modules\Content\ReadModel\PublishedServicePageReadService;
use App\Modules\Content\ReadModel\ServiceMediaCatalog;
use Database\Seeders\SeoServicePageSeeder;
use Database\Seeders\ServicePageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('all thirteen published service destinations have a deterministic approved media contract', function (): void {
    $expectedSlugs = [
        'mobilt-dekkskift',
        'dekkverksted',
        'tesla-og-elbil',
        'bobil-og-varebil',
        'dekkhotell',
        'dekk-felg-og-hjulpakker',
        'omlegging-balansering',
        'dekkreparasjon',
        'tpms-sensor',
        'oljeskift',
        'bilbatteri',
        'bildiagnose',
        'lyspaerer-viskerblad',
    ];

    expect(array_keys(config('service_media.services')))->toBe($expectedSlugs);

    foreach (config('service_media.assets') as $asset) {
        expect(public_path('brand/'.$asset['file']))->toBeFile();
        expect($asset['source'])->toStartWith('customer-local-');
    }

    $this->seed([ServicePageSeeder::class, SeoServicePageSeeder::class]);

    $services = app(PublishedServicePageReadService::class)->list(ServicePageLocale::NorwegianBokmal);
    $inventory = app(ServiceMediaCatalog::class)->inventory($services);

    expect($services)->toHaveCount(13)
        ->and(array_keys($inventory))->toBe($expectedSlugs)
        ->and(collect($inventory)->where('kind', 'repository'))->toHaveCount(9)
        ->and(collect($inventory)->where('kind', 'fallback'))->toHaveCount(4)
        ->and(collect($inventory)->pluck('source')->filter()->count())->toBe(13);
});

test('service index preserves all destinations and localized parity with the media contract', function (): void {
    $this->seed([ServicePageSeeder::class, SeoServicePageSeeder::class]);

    $services = app(PublishedServicePageReadService::class)->list(ServicePageLocale::NorwegianBokmal);
    $norwegian = $this->get('/tjenester')->assertOk();

    expect(substr_count($norwegian->getContent(), 'data-testid="service-card"'))->toBe(13);

    foreach ($services as $service) {
        $destination = $service->canonicalPath
            ? route('storefront.seo-services.show', $service->canonicalPath)
            : route('storefront.services.show', $service->slug);

        $norwegian->assertSee('data-service-slug="'.$service->slug.'"', false)
            ->assertSee('href="'.$destination.'"', false)
            ->assertSee($service->title);
    }

    config()->set('storefront.ru_preview_enabled', true);
    $russianServices = app(PublishedServicePageReadService::class)->list(ServicePageLocale::Russian);
    $russian = $this->get('/ru/tjenester')->assertOk();

    expect(substr_count($russian->getContent(), 'data-testid="service-card"'))->toBe(13);

    foreach ($russianServices as $service) {
        $russian->assertSee($service->title);
    }
});
