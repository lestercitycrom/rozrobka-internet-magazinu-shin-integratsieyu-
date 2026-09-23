<?php

use App\Models\User;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Enums\ServiceRequestFlow;
use App\Modules\Content\Models\ServicePage;
use App\Modules\Orders\Enums\FulfilmentMethod;
use App\Modules\Orders\Enums\NdiOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
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
        'preferred_date' => now()->addDays(2)->format('Y-m-d'),
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

    $this->actingAs($staff)->put('/admin/service-requests/'.$request->id, versionedServiceRequestPayload($request, [
        'name' => 'Updated Name', 'phone' => '91111111', 'status' => 'contacted', 'admin_note' => 'Ringte kunden',
    ]))->assertRedirect(route('admin.service-requests.show', $request));
    expect($request->fresh()->name)->toBe('Updated Name')
        ->and($request->fresh()->phone_normalized)->toBe('4791111111')
        ->and($request->fresh()->booking_slot_key)->toBe('b1-1-20990101-0900');

    $this->actingAs($staff)->put('/admin/service-requests/'.$request->id, versionedServiceRequestPayload($request, [
        'service_slug' => $service->slug,
        'fulfilment' => 'mobile',
        'booking_mode' => 'exact',
        'address' => 'Ringvegen 200',
        'name' => 'Updated Name',
        'phone' => '91111111',
        'status' => 'contacted',
    ]))->assertSessionHasErrors('fulfilment');
    expect($request->fresh()->fulfilment)->toBe('workshop');

    $request->update(['status' => ServiceRequestStatus::Cancelled]);
    $this->actingAs($staff)->put('/admin/service-requests/'.$request->id, versionedServiceRequestPayload($request, [
        'name' => 'Updated Name', 'phone' => '91111111', 'status' => 'new',
    ]))->assertSessionHasErrors('status');
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

test('full seasonal edit preserves vehicle sizes historical date and quote snapshot', function (): void {
    $service = issue57Service([
        'slug' => 'issue-57-seasonal',
        'request_flow' => ServiceRequestFlow::Seasonal,
    ]);
    $staff = User::factory()->operations()->create();
    $pastDate = now()->subDays(10)->format('Y-m-d');
    $vehicles = [
        ['vehicle' => 'Tesla Model Y', 'registration_number' => 'EV12345', 'tire_size' => '255/45 R19', 'vehicle_type' => 'passenger', 'rim_band' => 'r19', 'storage_plan' => 'renew'],
        ['vehicle' => 'Volvo XC60', 'registration_number' => 'EV54321', 'tire_size' => '235/55 R18', 'vehicle_type' => 'passenger', 'rim_band' => 'r16-r18', 'storage_plan' => 'none'],
    ];
    $request = ServiceRequest::create([
        'reference' => 'SR-PRESERVE-57', 'locale' => 'nb', 'source' => ServiceRequestSource::Public,
        'service_slug' => $service->slug, 'fulfilment' => 'workshop', 'preferred_date' => $pastDate,
        'preferred_window' => 'Ettermiddag', 'vehicle' => 'Tesla Model Y', 'registration_number' => 'EV12345',
        'tire_size' => '255/45 R19', 'dekkhotell' => true, 'vehicles' => $vehicles,
        'quote_min_minor' => 123400, 'quote_max_minor' => 123400, 'quote_currency' => 'NOK',
        'name' => 'Original Name', 'email' => 'original@example.test', 'phone' => '90000000',
        'phone_normalized' => '4790000000', 'note' => 'Original note', 'status' => ServiceRequestStatus::New,
        'consent_at' => now()->subMonth(),
    ]);

    $this->actingAs($staff)->get(route('admin.service-requests.edit', $request))
        ->assertOk()
        ->assertSee('name="vehicles[0][tire_size]" value="255/45 R19"', false)
        ->assertSee('name="vehicles[1][tire_size]" value="235/55 R18"', false)
        ->assertSee('min="'.$pastDate.'"', false);

    $this->actingAs($staff)->put(route('admin.service-requests.update', $request), versionedServiceRequestPayload($request, [
        'operational_form' => 1,
        'service_slug' => $service->slug,
        'fulfilment' => 'workshop',
        'booking_mode' => 'manual',
        'preferred_date' => $pastDate,
        'preferred_window' => 'Ettermiddag',
        'car_count' => 2,
        'vehicle' => 'Tesla Model Y',
        'registration_number' => 'EV12345',
        'tire_size' => '255/45 R19',
        'vehicle_type' => 'passenger',
        'rim_band' => 'r19',
        'storage_plan' => 'renew',
        'vehicle_2' => 'Volvo XC60',
        'registration_number_2' => 'EV54321',
        'tire_size_2' => '235/55 R18',
        'vehicle_type_2' => 'passenger',
        'rim_band_2' => 'r16-r18',
        'storage_plan_2' => 'none',
        'name' => 'Updated Name',
        'email' => 'original@example.test',
        'phone' => '90000000',
        'note' => 'Original note',
        'admin_note' => 'Contact corrected only',
        'status' => 'contacted',
    ]))->assertRedirect(route('admin.service-requests.show', $request));

    $request->refresh();
    expect($request->name)->toBe('Updated Name')
        ->and($request->preferred_date->format('Y-m-d'))->toBe($pastDate)
        ->and($request->vehicles)->toBe($vehicles)
        ->and($request->tire_size)->toBe('255/45 R19')
        ->and($request->quote_min_minor)->toBe(123400)
        ->and($request->quote_max_minor)->toBe(123400)
        ->and($request->quote_currency)->toBe('NOK');

    $this->actingAs($staff)->put(route('admin.service-requests.update', $request), versionedServiceRequestPayload($request, [
        'operational_form' => 1,
        'service_slug' => $service->slug,
        'fulfilment' => 'workshop',
        'booking_mode' => 'manual',
        'preferred_date' => $pastDate,
        'preferred_window' => 'Ettermiddag',
        'car_count' => 2,
        'vehicles' => array_map(
            static fn (array $vehicle): array => [...$vehicle, 'storage_number' => null],
            $vehicles,
        ),
        'name' => 'Updated Again',
        'email' => 'original@example.test',
        'phone' => '90000000',
        'note' => 'Original note',
        'admin_note' => 'Browser-shaped contact correction',
        'status' => 'contacted',
    ]))->assertRedirect(route('admin.service-requests.show', $request));

    $request->refresh();
    expect($request->vehicles)->toBe($vehicles)
        ->and($request->quote_min_minor)->toBe(123400)
        ->and($request->quote_max_minor)->toBe(123400);
});

test('partial seasonal edit with unchanged service preserves omitted multi vehicle details and quote', function (): void {
    $service = issue57Service([
        'slug' => 'issue-57-seasonal-partial',
        'request_flow' => ServiceRequestFlow::Seasonal,
    ]);
    $staff = User::factory()->operations()->create();
    $vehicles = [
        ['vehicle' => 'Tesla Model Y', 'registration_number' => 'EV12345', 'tire_size' => '255/45 R19', 'vehicle_type' => 'passenger', 'rim_band' => 'r19', 'storage_plan' => 'renew'],
        ['vehicle' => 'Volvo XC60', 'registration_number' => 'EV54321', 'tire_size' => '235/55 R18', 'vehicle_type' => 'passenger', 'rim_band' => 'r16-r18', 'storage_plan' => 'none'],
    ];
    $request = ServiceRequest::create([
        'reference' => 'SR-PARTIAL-PRESERVE-57', 'locale' => 'nb', 'source' => ServiceRequestSource::Public,
        'service_slug' => $service->slug, 'fulfilment' => 'workshop', 'preferred_date' => now()->addDays(2),
        'preferred_window' => 'Ettermiddag', 'vehicle' => 'Tesla Model Y', 'registration_number' => 'EV12345',
        'tire_size' => '255/45 R19', 'dekkhotell' => true, 'vehicles' => $vehicles,
        'quote_min_minor' => 234500, 'quote_max_minor' => 246700, 'quote_currency' => 'NOK',
        'name' => 'Original Name', 'phone' => '90000000', 'phone_normalized' => '4790000000',
        'status' => ServiceRequestStatus::New, 'consent_at' => now(),
    ]);

    $this->actingAs($staff)->patch(route('admin.service-requests.update', $request), versionedServiceRequestPayload($request, [
        'service_slug' => $service->slug,
        'fulfilment' => 'workshop',
        'booking_mode' => 'manual',
        'name' => 'Corrected Name',
        'phone' => '90000000',
    ]))->assertRedirect(route('admin.service-requests.show', $request));

    $request->refresh();
    expect($request->name)->toBe('Corrected Name')
        ->and($request->vehicles)->toBe($vehicles)
        ->and($request->dekkhotell)->toBeTrue()
        ->and($request->tire_size)->toBe('255/45 R19')
        ->and($request->quote_min_minor)->toBe(234500)
        ->and($request->quote_max_minor)->toBe(246700)
        ->and($request->quote_currency)->toBe('NOK');
});

test('partial service change to battery requires the resulting battery contract', function (): void {
    $generic = issue57Service(['slug' => 'issue-57-generic-before-battery']);
    $battery = issue57Service(['slug' => 'bilbatteri']);
    $staff = User::factory()->operations()->create();
    $request = ServiceRequest::create([
        'reference' => 'SR-PARTIAL-BATTERY-57', 'locale' => 'nb', 'source' => ServiceRequestSource::AdminManual,
        'created_by_user_id' => $staff->id, 'service_slug' => $generic->slug, 'fulfilment' => 'workshop',
        'name' => 'Battery Customer', 'phone' => '90000000', 'phone_normalized' => '4790000000',
        'status' => ServiceRequestStatus::New,
    ]);

    $this->actingAs($staff)->patch(route('admin.service-requests.update', $request), versionedServiceRequestPayload($request, [
        'service_slug' => $battery->slug,
        'fulfilment' => 'workshop',
        'booking_mode' => 'manual',
        'note' => 'Bilen starter tregt.',
        'name' => 'Battery Customer',
        'phone' => '90000000',
    ]))->assertSessionHasErrors(['preferred_date', 'registration_number']);

    expect($request->fresh()->service_slug)->toBe($generic->slug)
        ->and($request->fresh()->preferred_date)->toBeNull()
        ->and($request->fresh()->registration_number)->toBeNull();
});

test('partial fulfilment change to mobile requires a new address', function (): void {
    $service = issue57Service(['slug' => 'issue-57-mobile-change']);
    $staff = User::factory()->operations()->create();
    $request = ServiceRequest::create([
        'reference' => 'SR-PARTIAL-MOBILE-57', 'locale' => 'nb', 'source' => ServiceRequestSource::AdminManual,
        'created_by_user_id' => $staff->id, 'service_slug' => $service->slug, 'fulfilment' => 'workshop',
        'name' => 'Mobile Customer', 'phone' => '90000000', 'phone_normalized' => '4790000000',
        'status' => ServiceRequestStatus::New,
    ]);

    $this->actingAs($staff)->patch(route('admin.service-requests.update', $request), versionedServiceRequestPayload($request, [
        'service_slug' => $service->slug,
        'fulfilment' => 'mobile',
        'booking_mode' => 'manual',
        'name' => 'Mobile Customer',
        'phone' => '90000000',
    ]))->assertSessionHasErrors('address');

    expect($request->fresh()->fulfilment)->toBe('workshop')
        ->and($request->fresh()->address)->toBeNull();
});

test('full seasonal edit preserves an omitted single vehicle size but a genuine service change does not restore seasonal data', function (): void {
    $seasonal = issue57Service([
        'slug' => 'issue-57-seasonal-single',
        'request_flow' => ServiceRequestFlow::Seasonal,
    ]);
    $generic = issue57Service(['slug' => 'issue-57-generic']);
    $staff = User::factory()->operations()->create();
    $request = ServiceRequest::create([
        'reference' => 'SR-PRESERVE-SINGLE-57', 'locale' => 'nb', 'source' => ServiceRequestSource::Public,
        'service_slug' => $seasonal->slug, 'fulfilment' => 'workshop', 'preferred_date' => now()->addDays(2),
        'preferred_window' => 'Ettermiddag', 'vehicle' => 'Tesla Model Y', 'registration_number' => 'EV12345',
        'tire_size' => '255/45 R19', 'dekkhotell' => true,
        'vehicles' => [['vehicle' => 'Tesla Model Y', 'registration_number' => 'EV12345', 'tire_size' => '255/45 R19', 'vehicle_type' => 'passenger', 'rim_band' => 'r19', 'storage_plan' => 'renew']],
        'quote_min_minor' => 123400, 'quote_max_minor' => 123400, 'quote_currency' => 'NOK',
        'name' => 'Original Name', 'phone' => '90000000', 'phone_normalized' => '4790000000',
        'status' => ServiceRequestStatus::New, 'consent_at' => now(),
    ]);

    $this->actingAs($staff)->put(route('admin.service-requests.update', $request), versionedServiceRequestPayload($request, [
        'operational_form' => 1, 'service_slug' => $seasonal->slug, 'fulfilment' => 'workshop',
        'booking_mode' => 'manual', 'preferred_date' => $request->preferred_date->format('Y-m-d'),
        'preferred_window' => 'Ettermiddag', 'car_count' => 1, 'vehicle' => 'Tesla Model Y',
        'registration_number' => 'EV12345', 'vehicle_type' => 'passenger', 'rim_band' => 'r19',
        'storage_plan' => 'renew', 'name' => 'Contact corrected', 'phone' => '90000000', 'status' => 'contacted',
    ]))->assertRedirect();

    $request->refresh();
    expect($request->tire_size)->toBe('255/45 R19')
        ->and($request->vehicles[0]['tire_size'])->toBe('255/45 R19')
        ->and($request->quote_min_minor)->toBe(123400);

    $this->actingAs($staff)->put(route('admin.service-requests.update', $request), versionedServiceRequestPayload($request, [
        'operational_form' => 1, 'service_slug' => $generic->slug, 'fulfilment' => 'workshop',
        'booking_mode' => 'manual', 'preferred_date' => $request->preferred_date->format('Y-m-d'),
        'preferred_window' => 'Ettermiddag', 'name' => 'Service changed', 'phone' => '90000000', 'status' => 'contacted',
    ]))->assertRedirect();

    $request->refresh();
    expect($request->service_slug)->toBe($generic->slug)
        ->and($request->vehicles)->toBeNull()
        ->and($request->tire_size)->toBeNull()
        ->and($request->quote_min_minor)->toBeNull();
});

test('booked mobile request validates the effective stored fulfilment before clearing address', function (): void {
    $service = issue57Service();
    $staff = User::factory()->operations()->create();
    $request = ServiceRequest::create([
        'reference' => 'SR-MOBILE-57', 'locale' => 'nb', 'source' => ServiceRequestSource::Public,
        'service_slug' => $service->slug, 'fulfilment' => 'mobile', 'preferred_date' => now()->addDays(2),
        'preferred_window' => '09:00–09:30', 'booking_slot_key' => 'b1-1-20990101-0900',
        'booking_starts_at' => now()->addDays(2)->setTime(9, 0), 'booking_ends_at' => now()->addDays(2)->setTime(9, 30),
        'name' => 'Mobile Customer', 'phone' => '90000000', 'phone_normalized' => '4790000000',
        'address' => 'Ringvegen 200', 'status' => ServiceRequestStatus::New, 'consent_at' => now(),
    ]);

    $this->actingAs($staff)->put(route('admin.service-requests.update', $request), versionedServiceRequestPayload($request, [
        'name' => 'Mobile Customer', 'phone' => '90000000', 'address' => '', 'status' => 'contacted',
    ]))->assertSessionHasErrors('address');
    expect($request->fresh()->address)->toBe('Ringvegen 200');

    $this->actingAs($staff)->patch(route('admin.service-requests.update', $request), versionedServiceRequestPayload($request, [
        'name' => 'Updated Mobile Customer', 'phone' => '91111111', 'address' => 'Ringvegen 202', 'status' => 'contacted',
    ]))->assertRedirect();
    expect($request->fresh()->address)->toBe('Ringvegen 202')
        ->and($request->fresh()->name)->toBe('Updated Mobile Customer');
});

test('manual intake accepts only requestable services and keeps system provenance immutable', function (): void {
    $valid = issue57Service(['slug' => 'bilbatteri']);
    $draft = issue57Service(['slug' => 'draft-intake', 'publication_status' => ServicePagePublicationStatus::Draft]);
    $staff = User::factory()->operations()->create();
    $other = User::factory()->owner()->create();

    $this->actingAs($staff)->get('/admin/service-requests/create')
        ->assertOk()->assertSee('value="bilbatteri"', false)->assertDontSee('value="draft-intake"', false);
    $this->actingAs($staff)->post('/admin/service-requests', issue57Payload($draft))
        ->assertSessionHasErrors('service_slug');
    $this->actingAs($staff)->post('/admin/service-requests', issue57Payload($valid, ['note' => null]))
        ->assertSessionHasErrors(['preferred_date', 'registration_number', 'note']);
    $this->actingAs($staff)->post('/admin/service-requests', issue57Payload($valid, [
        'preferred_date' => now()->addDays(2)->format('Y-m-d'),
        'registration_number' => 'EV12345',
        'note' => 'Bilen starter tregt.',
        'status' => 'closed',
    ]))->assertSessionHasErrors('status');

    $this->actingAs($staff)->post('/admin/service-requests', issue57Payload($valid, [
        'preferred_date' => now()->addDays(2)->format('Y-m-d'),
        'registration_number' => 'EV12345',
        'note' => 'Bilen starter tregt.',
        'source' => 'public',
        'created_by_user_id' => $other->id,
    ]))->assertRedirect();
    $created = ServiceRequest::query()->sole();
    expect($created->source)->toBe(ServiceRequestSource::AdminManual)
        ->and($created->created_by_user_id)->toBe($staff->id)
        ->and($created->status)->toBe(ServiceRequestStatus::New);

    $historical = ServiceRequest::create([
        'reference' => 'SR-DRAFT-HISTORICAL', 'locale' => 'nb', 'source' => ServiceRequestSource::Public,
        'service_slug' => $draft->slug, 'fulfilment' => 'workshop', 'name' => 'Historical customer',
        'phone' => '92222222', 'phone_normalized' => '4792222222', 'status' => ServiceRequestStatus::New, 'consent_at' => now(),
    ]);
    $this->actingAs($staff)->patch(route('admin.service-requests.update', $historical), versionedServiceRequestPayload($historical, [
        'name' => 'Historical corrected', 'phone' => '92222222', 'status' => 'contacted',
    ]))->assertRedirect();
    expect($historical->fresh()->name)->toBe('Historical corrected')
        ->and($historical->fresh()->service_slug)->toBe($draft->slug);
});

test('request list supports inclusive combined date ranges and preserves filters in pagination', function (): void {
    $service = issue57Service();
    $staff = User::factory()->operations()->create();
    $from = now()->addDays(3)->format('Y-m-d');
    $to = now()->addDays(5)->format('Y-m-d');

    foreach (range(1, 27) as $index) {
        ServiceRequest::create([
            'reference' => sprintf('SR-RANGE-%02d', $index), 'locale' => 'nb', 'source' => ServiceRequestSource::AdminManual,
            'created_by_user_id' => $staff->id, 'service_slug' => $service->slug, 'fulfilment' => 'workshop',
            'preferred_date' => $index === 27 ? now()->addDays(8) : now()->addDays(4),
            'name' => 'Range Customer', 'phone' => '93333333', 'phone_normalized' => '4793333333',
            'status' => $index === 26 ? ServiceRequestStatus::Closed : ServiceRequestStatus::New,
        ]);
    }

    $query = http_build_query([
        'q' => 'Range Customer', 'date_from' => $from, 'date_to' => $to, 'service' => $service->slug,
        'fulfilment' => 'workshop', 'status' => 'new',
    ]);
    $response = $this->actingAs($staff)->get('/admin/service-requests?'.$query)
        ->assertOk()->assertSee('SR-RANGE-01')->assertDontSee('SR-RANGE-26')->assertDontSee('SR-RANGE-27');
    $response->assertSee('date_from='.$from, false)->assertSee('date_to='.$to, false);

    $this->actingAs($staff)->get('/admin/service-requests?date_from='.$to.'&date_to='.$from)
        ->assertSessionHasErrors(['date_from', 'date_to']);
    $this->actingAs($staff)->get('/admin/service-requests?date='.now()->addDays(8)->format('Y-m-d'))
        ->assertOk()->assertSee('SR-RANGE-27')->assertDontSee('SR-RANGE-01');
});

test('non owner roles are denied sensitive writes before controller mutation', function (): void {
    $service = issue57Service();
    $operations = User::factory()->operations()->create();
    $editor = User::factory()->editor()->create();
    $interval = BookingScheduleInterval::query()->create([
        'weekday' => 1, 'location' => 'workshop', 'start_time' => '09:00', 'end_time' => '10:00',
        'slot_minutes' => 30, 'capacity' => 1, 'is_active' => true,
    ]);
    $order = Order::query()->create([
        'id' => (string) Str::ulid(), 'public_reference' => 'ET-RBAC-57', 'idempotency_key' => (string) Str::uuid(),
        'locale' => 'nb', 'status' => OrderStatus::PendingReview, 'payment_status' => PaymentStatus::NotStarted,
        'ndi_status' => NdiOrderStatus::NotSubmitted, 'currency' => 'NOK', 'subtotal_minor' => 100,
        'total_minor' => 100, 'fulfilment_method' => FulfilmentMethod::Pickup, 'customer_name' => 'Protected buyer',
        'customer_email' => 'protected@example.test', 'customer_phone' => '94444444', 'terms_accepted_at' => now(),
        'privacy_accepted_at' => now(), 'pricing_snapshot_at' => now(), 'submitted_at' => now(),
    ]);
    $request = ServiceRequest::create([
        'reference' => 'SR-RBAC-WRITE-57', 'locale' => 'nb', 'source' => ServiceRequestSource::Public,
        'service_slug' => $service->slug, 'fulfilment' => 'workshop', 'name' => 'Protected customer',
        'phone' => '94444444', 'phone_normalized' => '4794444444', 'status' => ServiceRequestStatus::New, 'consent_at' => now(),
    ]);

    $this->actingAs($operations)->put('/admin/services/'.$service->id, ['title_nb' => 'Forbidden'])->assertForbidden();
    $this->actingAs($operations)->post('/admin/booking-calendar/intervals', [])->assertForbidden();
    $this->actingAs($operations)->delete('/admin/booking-calendar/intervals/'.$interval->id)->assertForbidden();
    $this->actingAs($operations)->post('/admin/orders/'.$order->id.'/transition', ['status' => 'awaiting_payment', 'note' => 'Forbidden'])->assertForbidden();
    $this->actingAs($operations)->post('/admin/orders/'.$order->id.'/payment/capture')->assertForbidden();
    $this->actingAs($operations)->put('/admin/catalog/publication', [])->assertForbidden();
    $this->actingAs($operations)->post('/admin/pricing-rules', [])->assertForbidden();
    $this->actingAs($operations)->put('/admin/storefront-settings', [])->assertForbidden();
    $this->actingAs($editor)->put(route('admin.service-requests.update', $request), versionedServiceRequestPayload($request, [
        'name' => 'Leaked edit', 'phone' => '94444444', 'status' => 'closed',
    ]))->assertForbidden();

    expect($service->fresh()->title_nb)->not->toBe('Forbidden')
        ->and(BookingScheduleInterval::query()->find($interval->id))->not->toBeNull()
        ->and($order->fresh()->status)->toBe(OrderStatus::PendingReview)
        ->and($request->fresh()->name)->toBe('Protected customer');
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
    ], $override);
}
