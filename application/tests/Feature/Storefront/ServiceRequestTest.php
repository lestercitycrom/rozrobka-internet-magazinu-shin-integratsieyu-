<?php

use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Models\ServicePage;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
test('buyer submits a non-binding service request with a public reference', function () {
    ServicePage::create(['slug' => 'test', 'sort_order' => 1, 'publication_status' => ServicePagePublicationStatus::Published, 'title_nb' => 'Test', 'title_ru' => 'Тест']);
    $this->post('/tjenester/test/foresporsel', ['fulfilment' => 'workshop', 'vehicle' => 'Tesla Model Y', 'registration_number' => 'AB12345', 'tire_size' => '205/55 R16', 'dekkhotell' => '1', 'name' => 'Buyer', 'email' => 'buyer@example.test', 'phone' => '1', 'consent' => '1'])->assertRedirect();
    expect(ServiceRequest::query()->first()->reference)->toStartWith('SR-');
    expect(ServiceRequest::query()->first()->vehicle)->toBe('Tesla Model Y')->and(ServiceRequest::query()->first()->dekkhotell)->toBeTrue();
});

test('service request form exposes accessible styled controls', function (): void {
    ServicePage::create(['slug' => 'form-test', 'sort_order' => 1, 'publication_status' => ServicePagePublicationStatus::Published, 'title_nb' => 'Test', 'title_ru' => 'Тест']);

    $this->get('/tjenester/form-test/foresporsel')
        ->assertOk()
        ->assertSee('for="booking-name"', false)
        ->assertSee('id="booking-fulfilment"', false)
        ->assertSee('id="booking-vehicle"', false)
        ->assertSee('id="booking-registration-number"', false)
        ->assertSee('id="booking-tire-size"', false)
        ->assertSee('Test')
        ->assertSee('type="checkbox"', false)
        ->assertSee('easytire-input', false);
});
test('honeypot rejects a service request', function () {
    $this->post('/tjenester/test/foresporsel', ['service_slug' => 'test', 'fulfilment' => 'workshop', 'name' => 'Buyer', 'email' => 'buyer@example.test', 'phone' => '1', 'consent' => '1', 'website' => 'bot'])->assertSessionHasErrors('website');
});
