<?php

use App\Models\User;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Enums\ServiceRequestFlow;
use App\Modules\Content\Models\ServicePage;
use App\Modules\Outbox\Models\OutboxMessage;
use App\Modules\ServiceBooking\Enums\ServiceRequestSource;
use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use App\Modules\ServiceBooking\Models\BookingScheduleInterval;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('operations staff create an unconfirmed request without email and can find it by normalized phone', function (): void {
    $service = issue57Service();
    $staff = User::factory()->operations()->create();
    $token = (string) Str::uuid();

    $response = $this->actingAs($staff)->post('/admin/service-requests', issue57Payload($service, [
        'submission_token' => $token,
        'email' => null,
        'phone' => '99 88 77 66',
        'preferred_date' => now()->addDays(3)->format('Y-m-d'),
        'preferred_window' => 'Etter kl. 14',
    ]));

    $request = ServiceRequest::query()->sole();
    $response->assertRedirect(route('admin.service-requests.show', $request));
    expect($request->source)->toBe(ServiceRequestSource::AdminManual)
        ->and($request->created_by_user_id)->toBe($staff->id)
        ->and($request->email)->toBeNull()
        ->and($request->consent_at)->toBeNull()
        ->and($request->phone_normalized)->toBe('4799887766')
        ->and($request->booking_slot_key)->toBeNull()
        ->and($request->preferred_window)->toBe('Etter kl. 14')
        ->and(OutboxMessage::query()->count())->toBe(0);

    $this->actingAs($staff)->get('/admin/service-requests?q=99+88+77+66')
        ->assertOk()->assertSee($request->reference)->assertSee('Kari Nordmann');
    $this->actingAs($staff)->get('/admin/service-requests?date='.$request->preferred_date->format('Y-m-d').'&service='.$service->slug.'&new_only=1')
        ->assertOk()->assertSee($request->reference);
});

test('manual submission tokens are idempotent only for the staff member who owns them', function (): void {
    $service = issue57Service();
    $staff = User::factory()->operations()->create();
    $other = User::factory()->operations()->create();
    $payload = issue57Payload($service);

    $first = $this->actingAs($staff)->post('/admin/service-requests', $payload)->assertRedirect();
    $second = $this->actingAs($staff)->post('/admin/service-requests', $payload)->assertRedirect();
    expect($second->headers->get('Location'))->toBe($first->headers->get('Location'))
        ->and(ServiceRequest::query()->count())->toBe(1);

    $this->actingAs($other)->post('/admin/service-requests', $payload)
        ->assertSessionHasErrors('submission_token');
    expect(ServiceRequest::query()->count())->toBe(1);
});

test('operations staff can confirm an exact available slot while creating a request', function (): void {
    $service = issue57Service();
    $staff = User::factory()->operations()->create();
    $date = now(config('service_booking.timezone'))->addDay()->startOfDay();
    BookingScheduleInterval::query()->create([
        'weekday' => $date->isoWeekday(),
        'location' => 'workshop',
        'start_time' => '09:00',
        'end_time' => '10:00',
        'slot_minutes' => 30,
        'capacity' => 1,
        'is_active' => true,
    ]);
    $slot = $this->getJson('/booking/availability?date='.$date->format('Y-m-d').'&fulfilment=workshop')
        ->assertOk()->json('slots.0');

    $this->actingAs($staff)->post('/admin/service-requests', issue57Payload($service, [
        'booking_mode' => 'exact',
        'preferred_date' => $date->format('Y-m-d'),
        'booking_slot_key' => $slot['key'],
    ]))->assertRedirect();

    $request = ServiceRequest::query()->sole();
    expect($request->booking_slot_key)->toBe($slot['key'])
        ->and($request->preferred_window)->toBe('09:00–09:30')
        ->and($request->booking_starts_at)->not->toBeNull()
        ->and(OutboxMessage::query()->count())->toBe(0);
});

test('manual intake accepts the complete mounting service contract', function (): void {
    $service = issue57Service([
        'slug' => 'omlegging-balansering',
        'title_nb' => 'Omlegging og balansering',
    ]);
    $staff = User::factory()->operations()->create();

    $this->actingAs($staff)->get('/admin/service-requests/create')
        ->assertOk()
        ->assertSee('mounting_balancing', false)
        ->assertSee('data-workshop-only="1"', false);

    $this->actingAs($staff)->post('/admin/service-requests', issue57Payload($service, [
        'service_variant' => 'mounting_balancing',
        'tire_size' => '205/55 R16',
        'wheel_count' => 4,
    ]))->assertRedirect();

    $request = ServiceRequest::query()->sole();
    expect($request->service_variant)->toBe('mounting_balancing')
        ->and($request->tire_size)->toBe('205/55 R16')
        ->and($request->wheel_count)->toBe(4)
        ->and($request->fulfilment)->toBe('workshop');
});

test('staff can edit contact and unconfirmed details while booked capacity data remains protected', function (): void {
    $service = issue57Service();
    $staff = User::factory()->operations()->create();
    $request = ServiceRequest::create([
        'reference' => 'SR-LOCKED-57',
        'locale' => 'nb',
        'source' => ServiceRequestSource::Public,
        'service_slug' => $service->slug,
        'fulfilment' => 'workshop',
        'preferred_date' => now()->addDays(4)->format('Y-m-d'),
        'preferred_window' => '09:00–09:30',
        'booking_slot_key' => 'b1-1-20990101-0900',
        'booking_starts_at' => now()->addDays(4)->setTime(9, 0),
        'booking_ends_at' => now()->addDays(4)->setTime(9, 30),
        'name' => 'Original Name',
        'phone' => '90000000',
        'phone_normalized' => '4790000000',
        'status' => ServiceRequestStatus::New,
        'consent_at' => now(),
    ]);

    $this->actingAs($staff)->put('/admin/service-requests/'.$request->id, [
        'name' => 'Updated Name', 'phone' => '91111111', 'status' => 'contacted', 'admin_note' => 'Ringte kunden',
    ])->assertRedirect(route('admin.service-requests.show', $request));
    expect($request->fresh()->name)->toBe('Updated Name')
        ->and($request->fresh()->phone_normalized)->toBe('4791111111')
        ->and($request->fresh()->booking_slot_key)->toBe('b1-1-20990101-0900');

    $this->actingAs($staff)->put('/admin/service-requests/'.$request->id, [
        'service_slug' => $service->slug,
        'fulfilment' => 'mobile',
        'booking_mode' => 'exact',
        'address' => 'Ringvegen 200',
        'name' => 'Updated Name',
        'phone' => '91111111',
        'status' => 'contacted',
    ])->assertSessionHasErrors('fulfilment');
    expect($request->fresh()->fulfilment)->toBe('workshop');

    $request->update(['status' => ServiceRequestStatus::Cancelled]);
    $this->actingAs($staff)->put('/admin/service-requests/'.$request->id, [
        'name' => 'Updated Name', 'phone' => '91111111', 'status' => 'new',
    ])->assertSessionHasErrors('status');
    expect($request->fresh()->status)->toBe(ServiceRequestStatus::Cancelled);
});

test('request details provide a safe encoded map link and both admin locales', function (): void {
    $service = issue57Service();
    $staff = User::factory()->operations()->russian()->create();
    $request = ServiceRequest::create([
        'reference' => 'SR-MAP-57', 'locale' => 'ru', 'source' => ServiceRequestSource::AdminManual,
        'created_by_user_id' => $staff->id, 'service_slug' => $service->slug, 'fulfilment' => 'mobile',
        'name' => 'Ирина', 'phone' => '+47 912 34 567', 'phone_normalized' => '4791234567',
        'address' => 'Ringvegen 200, 9018 Tromsø', 'status' => ServiceRequestStatus::New,
    ]);

    $this->actingAs($staff)->get('/admin/service-requests/'.$request->id)
        ->assertOk()->assertSee('Внесено сотрудником')->assertSee('Открыть адрес в Google Maps')
        ->assertSee('query=Ringvegen%20200%2C%209018%20Troms%C3%B8', false);
    $this->actingAs($staff)->get('/admin/service-requests/create')
        ->assertOk()->assertSee('Ручной ввод')->assertSee('E-mail (необязательно)');
});

test('admin roles enforce the accepted owner operations and editor boundaries', function (): void {
    issue57Service();
    $owner = User::factory()->owner()->create();
    $operations = User::factory()->operations()->create();
    $editor = User::factory()->editor()->create();
    $customer = User::factory()->customer()->create();

    $this->actingAs($owner)->get('/admin/orders')->assertOk();
    $this->actingAs($owner)->get('/admin/service-requests/create')->assertOk();
    $this->actingAs($operations)->get('/admin/service-requests/create')->assertOk();
    $this->actingAs($operations)->get('/admin/booking-calendar')->assertOk();
    $this->actingAs($operations)->get('/admin/orders')->assertForbidden();
    $this->actingAs($operations)->get('/admin/health')->assertForbidden();
    $this->actingAs($operations)->get('/admin/booking-calendar/intervals/create')->assertForbidden();
    $this->actingAs($operations)->get('/admin/services')->assertForbidden();
    $this->actingAs($editor)->get('/admin/services')->assertOk();
    $this->actingAs($editor)->get('/admin/media')->assertOk();
    $this->actingAs($editor)->get('/admin/service-requests')->assertForbidden();
    $this->actingAs($editor)->post('/admin/media/hero/product/999', ['media_asset_id' => 999])->assertForbidden();
    $this->actingAs($customer)->get('/admin')->assertForbidden();
});

function issue57Service(array $override = []): ServicePage
{
    return ServicePage::create(array_replace([
        'slug' => 'issue-57-service',
        'sort_order' => 1,
        'publication_status' => ServicePagePublicationStatus::Published,
        'request_flow' => ServiceRequestFlow::Generic,
        'title_nb' => 'Manuell tjeneste',
        'title_ru' => 'Ручная услуга',
    ], $override));
}

/** @return array<string, mixed> */
function issue57Payload(ServicePage $service, array $override = []): array
{
    return array_replace([
        'submission_token' => (string) Str::uuid(),
        'service_slug' => $service->slug,
        'fulfilment' => 'workshop',
        'booking_mode' => 'manual',
        'preferred_date' => null,
        'preferred_window' => null,
        'name' => 'Kari Nordmann',
        'email' => 'KARI@example.test',
        'phone' => '+47 900 00 000',
        'address' => null,
        'note' => 'Ring tilbake',
        'admin_note' => 'Telefonhenvendelse',
        'status' => 'new',
    ], $override);
}
