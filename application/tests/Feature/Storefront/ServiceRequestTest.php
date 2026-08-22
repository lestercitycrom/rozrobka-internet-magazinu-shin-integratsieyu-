<?php

use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Models\ServicePage;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
test('buyer submits a non-binding service request with a public reference', function () {
    ServicePage::create(['slug' => 'test', 'sort_order' => 1, 'publication_status' => ServicePagePublicationStatus::Published, 'title_nb' => 'Test', 'title_ru' => 'Тест']);
    $this->post('/tjenester/test/foresporsel', ['fulfilment' => 'workshop', 'vehicle' => 'Tesla Model Y', 'registration_number' => 'AB12345', 'tire_size' => '205/55 R16', 'dekkhotell' => '1', 'name' => 'Buyer', 'email' => 'buyer@example.test', 'phone' => '90000000', 'consent' => '1'])->assertRedirect();
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

test('mounting request requires and stores service variant and wheel count', function (): void {
    ServicePage::create(['slug' => 'omlegging-balansering', 'sort_order' => 1, 'publication_status' => ServicePagePublicationStatus::Published, 'title_nb' => 'Omlegging', 'title_ru' => 'Шиномонтаж']);

    $this->get('/tjenester/omlegging-balansering/foresporsel')
        ->assertOk()
        ->assertSee('id="booking-service-variant"', false)
        ->assertSee('id="booking-wheel-count"', false);

    $payload = ['fulfilment' => 'workshop', 'name' => 'Buyer', 'email' => 'buyer@example.test', 'phone' => '90000000', 'consent' => '1'];
    $this->post('/tjenester/omlegging-balansering/foresporsel', $payload)
        ->assertSessionHasErrors(['service_variant', 'wheel_count']);
    $this->post('/tjenester/omlegging-balansering/foresporsel', [...$payload, 'service_variant' => 'balancing_only', 'wheel_count' => '2', 'tire_size' => '205/55 R16', 'preferred_date' => now()->addDay()->format('Y-m-d')])
        ->assertRedirect();

    $stored = ServiceRequest::query()->latest('id')->firstOrFail();
    expect($stored->service_variant)->toBe('balancing_only')->and($stored->wheel_count)->toBe(2);
});

test('supported additional service requests store customer selected add-ons and diagnostic requests require a problem description', function (): void {
    foreach (['oljeskift', 'bilbatteri', 'bildiagnose', 'lyspaerer-viskerblad', 'tpms-sensor'] as $slug) {
        ServicePage::create(['slug' => $slug, 'sort_order' => 1, 'publication_status' => ServicePagePublicationStatus::Published, 'title_nb' => $slug, 'title_ru' => $slug]);
    }

    $payload = ['fulfilment' => 'workshop', 'name' => 'Buyer', 'email' => 'buyer@example.test', 'phone' => '90000000', 'consent' => '1'];
    $this->post('/tjenester/oljeskift/foresporsel', [...$payload, 'additional_services' => ['air_filter', 'cabin_filter']])->assertRedirect();
    expect(ServiceRequest::query()->latest('id')->firstOrFail()->additional_services)->toBe(['air_filter', 'cabin_filter']);

    foreach (['bilbatteri', 'bildiagnose', 'lyspaerer-viskerblad'] as $slug) {
        $response = $this->get("/tjenester/{$slug}/foresporsel")
            ->assertOk();

        foreach (['air_filter', 'cabin_filter', 'wiper_blades'] as $addon) {
            $response->assertSee('value="'.$addon.'"', false);
        }
    }

    $this->post('/tjenester/tpms-sensor/foresporsel', [
        ...$payload,
        'fulfilment' => 'workshop',
        'note' => 'Sensorfeil',
        'additional_services' => ['air_filter'],
    ])->assertSessionHasErrors('additional_services');

    $this->post('/tjenester/bildiagnose/foresporsel', $payload)->assertSessionHasErrors('note');
    $this->post('/tjenester/bildiagnose/foresporsel', [...$payload, 'note' => 'Motorlampen lyser'])->assertRedirect();
});

test('workbook service constraints are reflected in both UI and validation', function (): void {
    foreach (['tpms-sensor', 'bilbatteri', 'bildiagnose'] as $slug) {
        ServicePage::create(['slug' => $slug, 'sort_order' => 1, 'publication_status' => ServicePagePublicationStatus::Published, 'title_nb' => $slug, 'title_ru' => $slug]);
    }

    $this->get('/tjenester/tpms-sensor/foresporsel')
        ->assertOk()
        ->assertDontSee('<option value="mobile"', false);
    $this->post('/tjenester/tpms-sensor/foresporsel', [
        'fulfilment' => 'mobile', 'name' => 'Buyer', 'email' => 'buyer@example.test',
        'phone' => '1', 'note' => 'Sensorfeil', 'address' => 'Test 1', 'consent' => '1',
    ])->assertSessionHasErrors('fulfilment');

    foreach (['bilbatteri', 'bildiagnose'] as $slug) {
        $this->get("/tjenester/{$slug}/foresporsel")
            ->assertOk()
            ->assertSee('name="additional_services[]"', false)
            ->assertSee('value="air_filter"', false)
            ->assertSee('value="cabin_filter"', false)
            ->assertSee('value="wiper_blades"', false);
    }
});
