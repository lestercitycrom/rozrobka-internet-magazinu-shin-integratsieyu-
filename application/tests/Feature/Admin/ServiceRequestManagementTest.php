<?php

use App\Models\User;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Models\ServicePage;
use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('administrators can see and update a localized service request', function (): void {
    $admin = User::factory()->operations()->create();
    ServicePage::create(['slug' => 'dekkhotell', 'sort_order' => 1, 'publication_status' => ServicePagePublicationStatus::Published, 'title_nb' => 'Dekkhotell i Tromsø', 'title_ru' => 'Шинный отель в Тромсё']);
    $request = ServiceRequest::create([
        'reference' => 'SR-ADMIN-TEST', 'locale' => 'nb', 'service_slug' => 'dekkhotell', 'fulfilment' => 'mobile',
        'vehicle' => 'Tesla Model Y', 'registration_number' => 'AB12345', 'tire_size' => '205/55 R16', 'dekkhotell' => true,
        'name' => 'Buyer', 'email' => 'buyer@example.test', 'phone' => '123', 'status' => ServiceRequestStatus::New, 'consent_at' => now(),
    ]);

    $this->actingAs($admin)->get('/admin/service-requests')->assertOk()->assertSee('SR-ADMIN-TEST')->assertSee('Tjenesteforespørsler')->assertSee('Dekkhotell i Tromsø');
    $this->actingAs($admin)->get('/admin/service-requests/'.$request->id)->assertOk()->assertSee('Tesla Model Y')->assertSee('Registreringsnummer')->assertSee('Dekkhotell i Tromsø');
    $this->actingAs($admin)->put('/admin/service-requests/'.$request->id, ['status' => 'contacted', 'admin_note' => 'Ring kunden'])
        ->assertRedirect()->assertSessionHas('status');
    expect($request->fresh()->status)->toBe(ServiceRequestStatus::Contacted)->and($request->fresh()->admin_note)->toBe('Ring kunden');
});

test('service request administration is available in Russian for internal testing', function (): void {
    $admin = User::factory()->operations()->create(['locale' => 'ru']);

    $this->actingAs($admin)->get('/admin/service-requests')
        ->assertOk()
        ->assertSee('Заявки на услуги');
});
