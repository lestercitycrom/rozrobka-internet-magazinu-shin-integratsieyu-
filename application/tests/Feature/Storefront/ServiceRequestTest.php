<?php

use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Models\ServicePage;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
test('buyer submits a non-binding service request with a public reference', function () {
    ServicePage::create(['slug' => 'test', 'sort_order' => 1, 'publication_status' => ServicePagePublicationStatus::Published, 'title_nb' => 'Test', 'title_ru' => 'Тест']);
    $this->post('/tjenester/test/foresporsel', ['fulfilment' => 'workshop', 'name' => 'Buyer', 'email' => 'buyer@example.test', 'phone' => '1', 'consent' => '1'])->assertRedirect();
    expect(ServiceRequest::query()->first()->reference)->toStartWith('SR-');
});
test('honeypot rejects a service request', function () {
    $this->post('/tjenester/test/foresporsel', ['service_slug' => 'test', 'fulfilment' => 'workshop', 'name' => 'Buyer', 'email' => 'buyer@example.test', 'phone' => '1', 'consent' => '1', 'website' => 'bot'])->assertSessionHasErrors('website');
});
