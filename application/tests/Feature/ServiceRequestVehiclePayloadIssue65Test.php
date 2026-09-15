<?php

use App\Models\User;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Enums\ServiceRequestFlow;
use App\Modules\Content\Models\ServicePage;
use App\Modules\ServiceBooking\Models\BookingScheduleInterval;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use App\Modules\ServiceBooking\SeasonalQuoteCalculator;
use App\Modules\ServiceBooking\ServiceRequestVehiclePayload;
use Carbon\CarbonImmutable;
use Database\Seeders\PublicServicePriceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-14 10:00:00', 'Europe/Oslo'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

test('vehicle payload helper maps storage states and preserves text storage numbers', function (): void {
    expect(ServiceRequestVehiclePayload::generalPlan(false, false))->toBe('none')
        ->and(ServiceRequestVehiclePayload::generalPlan(true, false))->toBe('new')
        ->and(ServiceRequestVehiclePayload::generalPlan(true, true))->toBe('renew')
        ->and(ServiceRequestVehiclePayload::generalPlan(false, true))->toBe('last')
        ->and(ServiceRequestVehiclePayload::generalPlan(false, null))->toBe('unknown')
        ->and(ServiceRequestVehiclePayload::generalPlan(true, null))->toBe('unknown')
        ->and(ServiceRequestVehiclePayload::generalFacts('none'))->toBe([
            'dekkhotell' => false,
            'dekkhotell_already_stored' => false,
        ])
        ->and(ServiceRequestVehiclePayload::generalFacts('new'))->toBe([
            'dekkhotell' => true,
            'dekkhotell_already_stored' => false,
        ])
        ->and(ServiceRequestVehiclePayload::generalFacts('renew'))->toBe([
            'dekkhotell' => true,
            'dekkhotell_already_stored' => true,
        ])
        ->and(ServiceRequestVehiclePayload::generalFacts('last'))->toBe([
            'dekkhotell' => false,
            'dekkhotell_already_stored' => true,
        ])
        ->and(ServiceRequestVehiclePayload::requiresStoredSet('none'))->toBeFalse()
        ->and(ServiceRequestVehiclePayload::requiresStoredSet('new'))->toBeFalse()
        ->and(ServiceRequestVehiclePayload::requiresStoredSet('renew'))->toBeTrue()
        ->and(ServiceRequestVehiclePayload::requiresStoredSet('last'))->toBeTrue()
        ->and(ServiceRequestVehiclePayload::chargesNewStoragePeriod('none'))->toBeFalse()
        ->and(ServiceRequestVehiclePayload::chargesNewStoragePeriod('last'))->toBeFalse()
        ->and(ServiceRequestVehiclePayload::chargesNewStoragePeriod('new'))->toBeTrue()
        ->and(ServiceRequestVehiclePayload::chargesNewStoragePeriod('renew'))->toBeTrue();

    expect(ServiceRequestVehiclePayload::normalized([[
        'vehicle' => '  Tesla Model Y  ',
        'registration_number' => ' EV12345 ',
        'storage_number' => ' 00042-A ',
    ]]))->toBe([[
        'vehicle' => 'Tesla Model Y',
        'registration_number' => 'EV12345',
        'tire_size' => null,
        'vehicle_type' => 'passenger',
        'rim_band' => 'unknown',
        'storage_plan' => 'none',
        'storage_number' => '00042-A',
    ]]);
});

test('public canonical period payload accepts supported vehicle counts', function (int $count): void {
    $service = issue65Service('issue-65-public-'.$count, ServiceRequestFlow::Seasonal);
    $token = (string) Str::uuid();

    $this->post(route('storefront.requests.store', $service->slug), issue65PublicSeasonalPayload($count, [
        'submission_token' => $token,
    ]))->assertSessionHasNoErrors()->assertRedirect();

    $stored = ServiceRequest::query()->where('submission_token', $token)->sole();
    expect($stored->vehicles)->toHaveCount($count)
        ->and($stored->vehicle)->toBe('Issue 65 car 1')
        ->and($stored->vehicles[$count - 1]['vehicle'])->toBe('Issue 65 car '.$count);
})->with([3, 5, 8]);

test('public canonical period payload rejects a ninth vehicle atomically', function (): void {
    $service = issue65Service('issue-65-public-nine', ServiceRequestFlow::Seasonal);

    $this->post(route('storefront.requests.store', $service->slug), issue65PublicSeasonalPayload(9))
        ->assertSessionHasErrors(['car_count', 'vehicles']);

    expect(ServiceRequest::query()->count())->toBe(0)
        ->and(DB::table('service_request_submissions')->count())->toBe(0);
});

test('public seasonal payload rejects internal storage number injection', function (): void {
    $service = issue65Service('issue-65-public-storage-injection', ServiceRequestFlow::Seasonal);
    $nested = issue65PublicSeasonalPayload(1);
    $nested['vehicles'][0]['storage_number'] = '00042-A';

    $this->post(route('storefront.requests.store', $service->slug), $nested)
        ->assertSessionHasErrors('internal_storage_payload');

    $this->post(route('storefront.requests.store', $service->slug), issue65PublicSeasonalPayload(1, [
        'submission_token' => (string) Str::uuid(),
        'storage_number' => '00042-A',
    ]))->assertSessionHasErrors('storage_number');

    expect(ServiceRequest::query()->count())->toBe(0);
});

test('last storage plan does not add a new storage period surcharge', function (): void {
    $this->seed(PublicServicePriceSeeder::class);
    $calculator = app(SeasonalQuoteCalculator::class);
    $vehicle = [
        'vehicle_type' => 'passenger',
        'rim_band' => 'r19',
        'storage_plan' => 'last',
    ];

    expect($calculator->calculate('workshop', [$vehicle]))->toBe([
        'min_minor' => 65000,
        'max_minor' => 65000,
        'currency' => 'NOK',
    ])->and($calculator->calculate('workshop', [[...$vehicle, 'storage_plan' => 'renew']]))->toBe([
        'min_minor' => 160000,
        'max_minor' => 160000,
        'currency' => 'NOK',
    ]);
});

test('an exact request with three vehicles is rejected before it can consume capacity', function (): void {
    $service = issue65Service('issue-65-exact-capacity', ServiceRequestFlow::Seasonal);
    $date = CarbonImmutable::now('Europe/Oslo')->addDays(2)->startOfDay();
    $interval = BookingScheduleInterval::query()->create([
        'weekday' => $date->isoWeekday(),
        'location' => 'workshop',
        'start_time' => '15:00',
        'end_time' => '15:30',
        'slot_minutes' => 30,
        'capacity' => 1,
        'is_active' => true,
    ]);
    $slotKey = sprintf('b1-%d-%s-1500', $interval->id, $date->format('Ymd'));

    $this->post(route('storefront.requests.store', $service->slug), issue65PublicSeasonalPayload(3, [
        'booking_mode' => 'exact',
        'preferred_date' => $date->format('Y-m-d'),
        'preferred_time_start' => null,
        'preferred_time_end' => null,
        'booking_slot_key' => $slotKey,
    ]))->assertSessionHasErrors('booking_mode');

    expect(ServiceRequest::query()->count())->toBe(0)
        ->and(DB::table('service_request_submissions')->count())->toBe(0);

    $this->post(route('storefront.requests.store', $service->slug), issue65PublicSeasonalPayload(1, [
        'submission_token' => (string) Str::uuid(),
        'booking_mode' => 'exact',
        'preferred_date' => $date->format('Y-m-d'),
        'preferred_time_start' => null,
        'preferred_time_end' => null,
        'booking_slot_key' => $slotKey,
    ]))->assertSessionHasNoErrors()->assertRedirect();

    expect(ServiceRequest::query()->sole()->booking_slot_key)->toBe($slotKey);
});

test('admin canonical five vehicle storage numbers persist through a contact only edit', function (): void {
    $service = issue65Service('issue-65-admin-five', ServiceRequestFlow::Seasonal);
    $operations = User::factory()->operations()->create();
    $vehicles = collect(issue65Vehicles(5))->map(function (array $vehicle, int $index): array {
        return [...$vehicle,
            'storage_plan' => ['none', 'new', 'renew', 'last', 'none'][$index],
            'storage_number' => ['00042-A', 'DH-00002', 'DH-00003', 'DH-00004', null][$index],
        ];
    })->all();

    $this->actingAs($operations)->post(route('admin.service-requests.store'), [
        'submission_token' => (string) Str::uuid(),
        'service_slug' => $service->slug,
        'fulfilment' => 'workshop',
        'booking_mode' => 'manual',
        'preferred_date' => CarbonImmutable::now('Europe/Oslo')->addDays(2)->format('Y-m-d'),
        'vehicles' => $vehicles,
        'name' => 'Issue 65 admin customer',
        'phone' => '90000000',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $request = ServiceRequest::query()->sole();
    expect($request->vehicles)->toHaveCount(5)
        ->and(array_column($request->vehicles, 'storage_number'))->toBe([
            '00042-A', 'DH-00002', 'DH-00003', 'DH-00004', null,
        ])
        ->and($request->dekkhotell)->toBeTrue();

    $persistedVehicles = $request->vehicles;
    $this->actingAs($operations)->patch(route('admin.service-requests.update', $request), [
        'name' => 'Issue 65 corrected contact',
        'phone' => '91111111',
        'status' => 'contacted',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $request->refresh();
    expect($request->name)->toBe('Issue 65 corrected contact')
        ->and($request->vehicles)->toBe($persistedVehicles)
        ->and($request->vehicles[0]['storage_number'])->toBe('00042-A');
});

test('operations can add only a seasonal vehicle storage number after a public exact booking', function (): void {
    $this->seed(PublicServicePriceSeeder::class);
    $service = issue65Service('issue-65-exact-storage-number', ServiceRequestFlow::Seasonal);
    $operations = User::factory()->operations()->create();
    $date = CarbonImmutable::now('Europe/Oslo')->addDays(2)->startOfDay();
    $interval = BookingScheduleInterval::query()->create([
        'weekday' => $date->isoWeekday(),
        'location' => 'workshop',
        'start_time' => '09:00',
        'end_time' => '09:30',
        'slot_minutes' => 30,
        'capacity' => 1,
        'is_active' => true,
    ]);
    $slotKey = sprintf('b1-%d-%s-0900', $interval->id, $date->format('Ymd'));
    $vehicles = issue65Vehicles(2);
    $vehicles[0]['storage_plan'] = 'renew';

    $this->post(route('storefront.requests.store', $service->slug), issue65PublicSeasonalPayload(2, [
        'booking_mode' => 'exact',
        'preferred_date' => $date->format('Y-m-d'),
        'preferred_time_start' => null,
        'preferred_time_end' => null,
        'booking_slot_key' => $slotKey,
        'vehicles' => $vehicles,
    ]))->assertSessionHasNoErrors()->assertRedirect();

    $request = ServiceRequest::query()->sole();
    $this->actingAs($operations)->get(route('admin.service-requests.edit', $request))
        ->assertOk()
        ->assertSee('data-testid="booked-storage-number-0"', false)
        ->assertDontSee('data-testid="booked-storage-number-1"', false);
    $bookingKeys = [
        'booking_schedule_interval_id', 'booking_slot_key', 'booking_starts_at', 'booking_ends_at',
        'preferred_date', 'preferred_window',
    ];
    $quoteKeys = ['quote_min_minor', 'quote_max_minor', 'quote_currency'];
    $bookingSnapshot = array_intersect_key($request->getRawOriginal(), array_flip($bookingKeys));
    $quoteSnapshot = array_intersect_key($request->getRawOriginal(), array_flip($quoteKeys));
    $outboxSnapshot = DB::table('outbox_messages')->orderBy('id')->get()
        ->map(static fn (object $message): array => (array) $message)->all();
    $vehicleJsonSnapshot = $request->getRawOriginal('vehicles');

    $blankStorageVehicles = $request->vehicles;
    $blankStorageVehicles[0]['storage_number'] = '';
    $this->actingAs($operations)->patch(route('admin.service-requests.update', $request), [
        'vehicles' => $blankStorageVehicles,
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($request->fresh()->getRawOriginal('vehicles'))->toBe($vehicleJsonSnapshot);

    $expectedVehicles = $request->vehicles;
    $expectedVehicles[0]['storage_number'] = '00042-A';

    $submittedVehicles = $request->vehicles;
    $submittedVehicles[0]['storage_number'] = '00042-A';
    $this->actingAs($operations)->patch(route('admin.service-requests.update', $request), [
        'vehicles' => $submittedVehicles,
        'admin_note' => 'Storage number assigned after arrival.',
        'quote_min_minor' => 1,
        'quote_max_minor' => 1,
        'quote_currency' => 'XXX',
    ])->assertSessionHasNoErrors()->assertRedirect(route('admin.service-requests.show', $request));

    $request->refresh();
    expect($request->vehicles)->toBe($expectedVehicles)
        ->and($request->registration_number)->toBe('IS650001')
        ->and(array_intersect_key($request->getRawOriginal(), array_flip($bookingKeys)))->toBe($bookingSnapshot)
        ->and(array_intersect_key($request->getRawOriginal(), array_flip($quoteKeys)))->toBe($quoteSnapshot)
        ->and(DB::table('outbox_messages')->orderBy('id')->get()
            ->map(static fn (object $message): array => (array) $message)->all())->toBe($outboxSnapshot);

    $forgedRegistration = $request->vehicles;
    $forgedRegistration[0]['registration_number'] = 'FORGED1';
    $this->actingAs($operations)->patch(route('admin.service-requests.update', $request), [
        'vehicles' => $forgedRegistration,
    ])->assertSessionHasErrors('vehicles');

    $forgedPlan = $request->vehicles;
    $forgedPlan[0]['storage_plan'] = 'last';
    $this->actingAs($operations)->patch(route('admin.service-requests.update', $request), [
        'vehicles' => $forgedPlan,
    ])->assertSessionHasErrors('vehicles');

    $forgedComposition = $request->vehicles;
    $forgedComposition[] = [
        'vehicle' => 'Injected vehicle', 'registration_number' => 'FORGED2', 'tire_size' => '205/55 R16',
        'vehicle_type' => 'passenger', 'rim_band' => 'r16-r18', 'storage_plan' => 'none',
        'storage_number' => 'INJECTED',
    ];
    $this->actingAs($operations)->patch(route('admin.service-requests.update', $request), [
        'vehicles' => $forgedComposition,
    ])->assertSessionHasErrors('vehicles');

    $this->actingAs($operations)->patch(route('admin.service-requests.update', $request), [
        'booking_slot_key' => 'invalid',
    ])->assertSessionHasErrors('booking_slot_key');

    $forgedInactiveStorage = $request->vehicles;
    $forgedInactiveStorage[1]['storage_number'] = 'FORGED-NONE';
    $this->actingAs($operations)->patch(route('admin.service-requests.update', $request), [
        'vehicles' => $forgedInactiveStorage,
    ])->assertSessionHasErrors('vehicles.1.storage_number');

    $request->refresh();
    expect($request->vehicles)->toBe($expectedVehicles)
        ->and(array_intersect_key($request->getRawOriginal(), array_flip($bookingKeys)))->toBe($bookingSnapshot)
        ->and(array_intersect_key($request->getRawOriginal(), array_flip($quoteKeys)))->toBe($quoteSnapshot)
        ->and(DB::table('outbox_messages')->orderBy('id')->get()
            ->map(static fn (object $message): array => (array) $message)->all())->toBe($outboxSnapshot);
});

test('booked general storage updates do not require resubmitting a stored registration', function (): void {
    $service = issue65Service('dekkreparasjon', ServiceRequestFlow::Generic);
    $operations = User::factory()->operations()->create();
    $date = CarbonImmutable::now('Europe/Oslo')->addDays(2)->startOfDay();
    $interval = BookingScheduleInterval::query()->create([
        'weekday' => $date->isoWeekday(),
        'location' => 'workshop',
        'start_time' => '10:00',
        'end_time' => '10:30',
        'slot_minutes' => 30,
        'capacity' => 1,
        'is_active' => true,
    ]);
    $slotKey = sprintf('b1-%d-%s-1000', $interval->id, $date->format('Ymd'));

    $this->post(route('storefront.requests.store', $service->slug), [
        'submission_token' => (string) Str::uuid(),
        'fulfilment' => 'workshop',
        'booking_mode' => 'exact',
        'preferred_date' => $date->format('Y-m-d'),
        'booking_slot_key' => $slotKey,
        'vehicle' => 'General tyre car',
        'registration_number' => 'GEN12345',
        'dekkhotell' => '1',
        'name' => 'General exact customer',
        'phone' => '90000000',
        'consent' => '1',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $request = ServiceRequest::query()->sole();
    $this->actingAs($operations)->get(route('admin.service-requests.edit', $request))
        ->assertOk()
        ->assertSee('data-testid="booked-storage-number-general"', false);
    $this->actingAs($operations)->patch(route('admin.service-requests.update', $request), [
        'name' => 'General contact corrected',
        'admin_note' => 'Contact-only follow-up.',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->actingAs($operations)->put(route('admin.service-requests.update', $request), [
        'name' => 'General contact corrected',
        'phone' => '90000000',
        'status' => 'contacted',
        'storage_number' => '00042-A',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $request->refresh();
    expect($request->registration_number)->toBe('GEN12345')
        ->and($request->dekkhotell)->toBeTrue()
        ->and($request->dekkhotell_number)->toBe('00042-A')
        ->and($request->booking_slot_key)->toBe($slotKey);

    $this->actingAs($operations)->put(route('admin.service-requests.update', $request), [
        'name' => 'General contact corrected again',
        'phone' => '90000000',
        'status' => 'contacted',
        'storage_number' => '00042-A',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->actingAs($operations)->put(route('admin.service-requests.update', $request), [
        'operational_form' => '1',
        'service_slug' => $service->slug,
        'fulfilment' => 'workshop',
        'booking_mode' => 'exact',
        'preferred_date' => $date->format('Y-m-d'),
        'booking_slot_key' => $slotKey,
        'vehicle' => 'General tyre car',
        'registration_number' => '',
        'storage_plan' => 'unknown',
        'name' => 'General contact corrected again',
        'phone' => '90000000',
        'status' => 'contacted',
    ])->assertSessionHasErrors('registration_number');

    $inactive = ServiceRequest::query()->create([
        'reference' => 'SR-ISSUE-65-INACTIVE-STORAGE',
        'locale' => 'nb',
        'source' => 'admin_manual',
        'service_slug' => $service->slug,
        'fulfilment' => 'workshop',
        'dekkhotell' => false,
        'dekkhotell_already_stored' => false,
        'name' => 'Inactive storage customer',
        'phone' => '91111111',
        'phone_normalized' => '4791111111',
        'status' => 'new',
    ]);
    $this->actingAs($operations)->patch(route('admin.service-requests.update', $inactive), [
        'service_slug' => $service->slug,
        'fulfilment' => 'workshop',
        'booking_mode' => 'manual',
        'storage_plan' => 'new',
    ])->assertSessionHasErrors('registration_number');

    expect($request->fresh()->registration_number)->toBe('GEN12345')
        ->and($request->dekkhotell_number)->toBe('00042-A')
        ->and($inactive->fresh()->dekkhotell)->toBeFalse();
});

test('generic public requests keep vehicles null', function (): void {
    $service = issue65Service('issue-65-generic', ServiceRequestFlow::Generic);

    $this->post(route('storefront.requests.store', $service->slug), [
        'submission_token' => (string) Str::uuid(),
        'fulfilment' => 'workshop',
        'name' => 'Issue 65 generic customer',
        'phone' => '92222222',
        'consent' => '1',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $request = ServiceRequest::query()->sole();
    expect($request->vehicles)->toBeNull()
        ->and($request->quote_min_minor)->toBeNull()
        ->and($request->quote_max_minor)->toBeNull();
});

function issue65Service(string $slug, ServiceRequestFlow $flow): ServicePage
{
    return ServicePage::query()->create([
        'slug' => $slug,
        'sort_order' => 1,
        'publication_status' => ServicePagePublicationStatus::Published,
        'request_flow' => $flow,
        'title_nb' => 'Issue 65 service',
        'title_ru' => 'Issue 65 service',
    ]);
}

/** @return list<array<string, string|null>> */
function issue65Vehicles(int $count): array
{
    return array_map(static fn (int $number): array => [
        'vehicle' => 'Issue 65 car '.$number,
        'registration_number' => sprintf('IS65%04d', $number),
        'tire_size' => '205/55 R16',
        'vehicle_type' => 'passenger',
        'rim_band' => 'r16-r18',
        'storage_plan' => 'none',
    ], range(1, $count));
}

/** @param array<string, mixed> $override @return array<string, mixed> */
function issue65PublicSeasonalPayload(int $count, array $override = []): array
{
    return array_replace([
        'submission_token' => (string) Str::uuid(),
        'fulfilment' => 'workshop',
        'booking_mode' => 'period',
        'preferred_date' => CarbonImmutable::now('Europe/Oslo')->addDays(2)->format('Y-m-d'),
        'preferred_time_start' => '10:00',
        'preferred_time_end' => '12:00',
        'vehicles' => issue65Vehicles($count),
        'name' => 'Issue 65 public customer',
        'phone' => '90000000',
        'consent' => '1',
    ], $override);
}
