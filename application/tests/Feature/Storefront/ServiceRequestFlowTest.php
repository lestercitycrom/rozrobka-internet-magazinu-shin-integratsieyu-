<?php

use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Enums\ServiceRequestFlow;
use App\Modules\Content\Models\ServicePage;
use Database\Seeders\ServicePageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('persisted request flow selects seasonal and generic production forms', function (): void {
    ServicePage::create([
        'slug' => 'custom-seasonal',
        'sort_order' => 1,
        'publication_status' => ServicePagePublicationStatus::Published,
        'request_flow' => ServiceRequestFlow::Seasonal,
        'title_nb' => 'Sesongtjeneste',
        'title_ru' => 'Сезонная услуга',
    ]);
    ServicePage::create([
        'slug' => 'mobilt-dekkskift',
        'sort_order' => 2,
        'publication_status' => ServicePagePublicationStatus::Published,
        'request_flow' => ServiceRequestFlow::Generic,
        'title_nb' => 'Eksplisitt vanlig',
        'title_ru' => 'Явно обычная',
    ]);

    $this->get('/tjenester/custom-seasonal/foresporsel')
        ->assertOk()
        ->assertSee('data-testid="seasonal-service-request"', false)
        ->assertDontSee('data-testid="generic-service-request"', false);

    $this->get('/tjenester/mobilt-dekkskift/foresporsel')
        ->assertOk()
        ->assertSee('data-testid="generic-service-request"', false)
        ->assertDontSee('data-testid="seasonal-service-request"', false);
});

test('real dekkverksted slug is seasonal after the production seed', function (): void {
    $this->seed(ServicePageSeeder::class);

    $page = ServicePage::query()->where('slug', 'dekkverksted')->firstOrFail();
    expect($page->request_flow)->toBe(ServiceRequestFlow::Seasonal);

    $this->get('/tjenester/dekkverksted/foresporsel')
        ->assertOk()
        ->assertSee('data-testid="seasonal-service-request"', false)
        ->assertDontSee('data-testid="generic-service-request"', false);
});
