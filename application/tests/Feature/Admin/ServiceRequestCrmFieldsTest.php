<?php

use App\Models\User;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Enums\ServiceRequestFlow;
use App\Modules\Content\Models\ServicePage;
use App\Modules\ServiceBooking\Enums\ServiceRequestSource;
use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-14 10:00:00', 'Europe/Oslo'));
});

afterEach(fn () => CarbonImmutable::setTestNow());

test('public seasonal period district and storage status survive through the admin card', function (): void {
    $service = issue59CrmService('issue-59-seasonal', ServiceRequestFlow::Seasonal);
    $operations = User::factory()->operations()->create();
    $date = now('Europe/Oslo')->addDays(3)->format('Y-m-d');

    $this->post(route('storefront.requests.store', $service->slug), issue59SeasonalPayload($date, [
        'booking_mode' => 'period',
        'preferred_time_start' => '13:15',
        'preferred_time_end' => '15:45',
        'fulfilment' => 'mobile',
        'address' => 'Sommervegen 10',
        'district' => 'Tromsdalen',
        'storage_plan' => 'renew',
        'registration_number' => 'EV12345',
    ]))->assertRedirect();

    $request = ServiceRequest::query()->sole();
    expect($request->booking_slot_key)->toBeNull()
        ->and($request->booking_starts_at)->toBeNull()
        ->and($request->preferred_period_starts_at->setTimezone('Europe/Oslo')->format('Y-m-d H:i'))->toBe("{$date} 13:15")
        ->and($request->preferred_period_ends_at->setTimezone('Europe/Oslo')->format('Y-m-d H:i'))->toBe("{$date} 15:45")
        ->and($request->preferred_window)->toBe('13:15–15:45')
        ->and($request->district)->toBe('Tromsdalen')
        ->and($request->dekkhotell)->toBeTrue()
        ->and($request->vehicles[0]['storage_plan'])->toBe('renew');

    $this->actingAs($operations)->get(route('admin.service-requests.show', $request))
        ->assertOk()->assertSee('Tromsdalen')->assertSee('13:15–15:45');
});

test('storage requires a registration for every selected seasonal vehicle', function (): void {
    $service = issue59CrmService('issue-59-storage', ServiceRequestFlow::Seasonal);
    $date = now('Europe/Oslo')->addDays(3)->format('Y-m-d');

    $this->post(route('storefront.requests.store', $service->slug), issue59SeasonalPayload($date, [
        'booking_mode' => 'period', 'preferred_time_start' => '09:00', 'preferred_time_end' => '11:00',
        'storage_plan' => 'new', 'registration_number' => null,
    ]))->assertSessionHasErrors('registration_number');

    $this->post(route('storefront.requests.store', $service->slug), issue59SeasonalPayload($date, [
        'booking_mode' => 'period', 'preferred_time_start' => '09:00', 'preferred_time_end' => '11:00',
        'car_count' => 2, 'storage_plan' => 'none',
        'vehicle_2' => 'Volvo XC60', 'vehicle_type_2' => 'passenger', 'rim_band_2' => 'r16-r18',
        'storage_plan_2' => 'renew', 'registration_number_2' => null,
    ]))->assertSessionHasErrors('registration_number_2');

    expect(ServiceRequest::query()->count())->toBe(0);
});

test('an operator can explicitly remove storage without leaving a false dekkhotell flag', function (): void {
    $service = issue59CrmService('issue-59-storage-edit', ServiceRequestFlow::Seasonal);
    $operations = User::factory()->operations()->create();
    $date = now('Europe/Oslo')->addDays(3)->format('Y-m-d');

    $this->actingAs($operations)->post(route('admin.service-requests.store'), issue59AdminPayload($service, [
        'operational_form' => '1', 'booking_mode' => 'period', 'preferred_date' => $date,
        'preferred_time_start' => '09:00', 'preferred_time_end' => '11:00',
        'car_count' => 1, 'vehicle' => 'Tesla Model Y', 'vehicle_type' => 'passenger',
        'rim_band' => 'r19', 'storage_plan' => 'renew', 'registration_number' => 'EV12345',
    ]))->assertRedirect();
    $request = ServiceRequest::query()->sole();
    expect($request->dekkhotell)->toBeTrue();

    $this->actingAs($operations)->patch(route('admin.service-requests.update', $request), issue59AdminPayload($service, [
        'operational_form' => '1', 'booking_mode' => 'period', 'preferred_date' => $date,
        'preferred_time_start' => '09:00', 'preferred_time_end' => '11:00',
        'car_count' => 1, 'vehicle' => 'Tesla Model Y', 'vehicle_type' => 'passenger',
        'rim_band' => 'r19', 'storage_plan' => 'none', 'registration_number' => 'EV12345',
    ]))->assertRedirect();

    $request->refresh();
    expect($request->dekkhotell)->toBeFalse()
        ->and($request->vehicles[0]['storage_plan'])->toBe('none')
        ->and($request->vehicles[0]['registration_number'])->toBe('EV12345');
});

test('legacy storage without registration remains editable but a new storage selection requires registration', function (): void {
    $service = issue59CrmService('issue-59-legacy-storage', ServiceRequestFlow::Seasonal);
    $operations = User::factory()->operations()->create();
    $date = now('Europe/Oslo')->addDays(2)->format('Y-m-d');
    $legacy = ServiceRequest::query()->create([
        'reference' => 'SR-CRM-LEGACY-STORAGE', 'locale' => 'nb', 'source' => ServiceRequestSource::Public,
        'service_slug' => $service->slug, 'fulfilment' => 'workshop', 'preferred_date' => $date,
        'preferred_window' => 'Ettermiddag', 'vehicle' => 'Legacy car', 'registration_number' => null,
        'dekkhotell' => true, 'vehicles' => [[
            'vehicle' => 'Legacy car', 'registration_number' => null, 'tire_size' => '205/55 R16',
            'vehicle_type' => 'passenger', 'rim_band' => 'r16-r18', 'storage_plan' => 'renew',
        ]],
        'name' => 'Legacy storage customer', 'phone' => '90000000', 'phone_normalized' => '4790000000',
        'status' => ServiceRequestStatus::New,
    ]);

    $unchangedStorage = issue59AdminPayload($service, [
        'operational_form' => '1', 'booking_mode' => 'manual', 'preferred_date' => $date,
        'preferred_window' => 'Ettermiddag', 'car_count' => 1, 'vehicle' => 'Legacy car',
        'vehicle_type' => 'passenger', 'rim_band' => 'r16-r18', 'storage_plan' => 'renew',
        'registration_number' => null, 'name' => 'Legacy contact updated',
    ]);
    $this->actingAs($operations)->put(route('admin.service-requests.update', $legacy), $unchangedStorage)
        ->assertRedirect();
    $legacy->refresh();
    expect($legacy->name)->toBe('Legacy contact updated')
        ->and($legacy->vehicles[0]['storage_plan'])->toBe('renew')
        ->and($legacy->vehicles[0]['registration_number'])->toBeNull();

    $this->actingAs($operations)->put(route('admin.service-requests.update', $legacy), [
        ...$unchangedStorage, 'storage_plan' => 'new', 'registration_number' => null,
    ])->assertSessionHasErrors('registration_number');
    expect($legacy->refresh()->vehicles[0]['storage_plan'])->toBe('renew');
});

test('general tyre storage records both explicit already-stored answers and preserves legacy unknown', function (): void {
    $service = issue59CrmService('omlegging-balansering');
    $operations = User::factory()->operations()->create();
    $date = now('Europe/Oslo')->addDays(2)->format('Y-m-d');
    $payload = issue59AdminPayload($service, [
        'operational_form' => '1', 'booking_mode' => 'manual', 'preferred_date' => $date,
        'service_variant' => 'mounting_balancing', 'wheel_count' => 4, 'tire_size' => '205/55 R16',
        'dekkhotell_control' => '1', 'dekkhotell' => '1', 'registration_number' => null,
    ]);

    $this->actingAs($operations)->post(route('admin.service-requests.store'), $payload)
        ->assertSessionHasErrors(['registration_number', 'dekkhotell_already_stored']);
    $this->actingAs($operations)->post(route('admin.service-requests.store'), [
        ...$payload, 'submission_token' => (string) Str::uuid(), 'registration_number' => 'ZH12345',
    ])->assertSessionHasErrors('dekkhotell_already_stored');
    $this->actingAs($operations)->post(route('admin.service-requests.store'), [
        ...$payload, 'submission_token' => (string) Str::uuid(), 'registration_number' => 'ZH12345',
        'dekkhotell_already_stored' => '1',
    ])->assertRedirect();

    $request = ServiceRequest::query()->sole();
    expect($request->dekkhotell)->toBeTrue()
        ->and($request->dekkhotell_already_stored)->toBeTrue()
        ->and($request->registration_number)->toBe('ZH12345');
    $this->actingAs($operations)->get(route('admin.service-requests.show', $request))
        ->assertOk()->assertSee('data-testid="general-storage-already"', false)
        ->assertSee('Er hjulene allerede lagret hos EasyTire?');
    $this->actingAs($operations)->get(route('admin.service-requests.edit', $request))
        ->assertOk()->assertSee('data-general-storage-answer', false)
        ->assertSee('name="dekkhotell_already_stored"', false);

    $this->actingAs($operations)->put(route('admin.service-requests.update', $request), [
        ...$payload, 'registration_number' => 'ZH12345', 'dekkhotell_already_stored' => '0',
    ])->assertRedirect();
    expect($request->refresh()->dekkhotell_already_stored)->toBeFalse();

    $this->actingAs($operations)->put(route('admin.service-requests.update', $request), [
        ...$payload, 'registration_number' => 'ZH12345', 'dekkhotell' => null,
        'dekkhotell_already_stored' => '0',
    ])->assertRedirect();
    expect($request->refresh()->dekkhotell)->toBeFalse()
        ->and($request->dekkhotell_already_stored)->toBeNull()
        ->and($request->registration_number)->toBe('ZH12345');

    $legacy = ServiceRequest::query()->create([
        'reference' => 'SR-CRM-LEGACY-GENERAL-STORAGE', 'locale' => 'nb',
        'source' => ServiceRequestSource::AdminManual, 'service_slug' => $service->slug,
        'fulfilment' => 'workshop', 'dekkhotell' => true, 'dekkhotell_already_stored' => null,
        'registration_number' => 'LEGACY1', 'name' => 'Legacy general storage',
        'phone' => '90000000', 'phone_normalized' => '4790000000', 'status' => ServiceRequestStatus::New,
    ]);
    $this->actingAs($operations)->get(route('admin.service-requests.show', $legacy))
        ->assertOk()->assertSee('Er hjulene allerede lagret hos EasyTire?')->assertSee('Ikke oppgitt');
    $this->actingAs($operations)->get(route('admin.service-requests.edit', $legacy))
        ->assertOk()->assertSee('Ikke registrert i den tidligere forespørselen.');
    $this->actingAs($operations)->patch(route('admin.service-requests.update', $legacy), [
        'name' => 'Legacy contact corrected', 'phone' => '91111111', 'status' => 'contacted',
    ])->assertRedirect();
    expect($legacy->refresh()->dekkhotell)->toBeTrue()
        ->and($legacy->dekkhotell_already_stored)->toBeNull();
});

test('partial storage change for a stored second vehicle requires its registration atomically', function (): void {
    $service = issue59CrmService('issue-59-second-car-patch', ServiceRequestFlow::Seasonal);
    $operations = User::factory()->operations()->create();
    $date = now('Europe/Oslo')->addDays(2)->format('Y-m-d');
    $request = ServiceRequest::query()->create([
        'reference' => 'SR-CRM-SECOND-CAR-PATCH', 'locale' => 'nb',
        'source' => ServiceRequestSource::AdminManual, 'service_slug' => $service->slug,
        'fulfilment' => 'workshop', 'preferred_date' => $date, 'vehicle' => 'Tesla Model Y',
        'registration_number' => 'EV12345', 'dekkhotell' => false, 'vehicles' => [
            ['vehicle' => 'Tesla Model Y', 'registration_number' => 'EV12345', 'tire_size' => '255/45 R19', 'vehicle_type' => 'passenger', 'rim_band' => 'r19', 'storage_plan' => 'none'],
            ['vehicle' => 'Volvo XC60', 'registration_number' => null, 'tire_size' => '235/55 R18', 'vehicle_type' => 'passenger', 'rim_band' => 'r16-r18', 'storage_plan' => 'none'],
        ],
        'name' => 'Second car storage', 'phone' => '90000000', 'phone_normalized' => '4790000000',
        'status' => ServiceRequestStatus::New,
    ]);
    $partial = [
        'service_slug' => $service->slug, 'fulfilment' => 'workshop', 'booking_mode' => 'manual',
        'storage_plan_2' => 'renew',
    ];

    $this->actingAs($operations)->patch(route('admin.service-requests.update', $request), $partial)
        ->assertSessionHasErrors('registration_number_2');
    $request->refresh();
    expect($request->vehicles)->toHaveCount(2)
        ->and($request->vehicles[1]['storage_plan'])->toBe('none')
        ->and($request->vehicles[1]['registration_number'])->toBeNull();

    $this->actingAs($operations)->patch(route('admin.service-requests.update', $request), [
        ...$partial, 'registration_number_2' => 'EV54321',
    ])->assertRedirect();
    $request->refresh();
    expect($request->vehicles)->toHaveCount(2)
        ->and($request->vehicles[1]['storage_plan'])->toBe('renew')
        ->and($request->vehicles[1]['registration_number'])->toBe('EV54321')
        ->and($request->dekkhotell)->toBeTrue();
});

test('period validation is atomic while a historical contact-only edit stays possible', function (): void {
    $service = issue59CrmService('issue-59-admin-period');
    $operations = User::factory()->operations()->create();
    $date = now('Europe/Oslo')->addDays(2)->format('Y-m-d');

    $this->actingAs($operations)->post(route('admin.service-requests.store'), issue59AdminPayload($service, [
        'booking_mode' => 'period', 'preferred_date' => $date,
        'preferred_time_start' => '15:00', 'preferred_time_end' => '12:00',
    ]))->assertSessionHasErrors('preferred_time_end');
    expect(ServiceRequest::query()->count())->toBe(0);

    $this->actingAs($operations)->post(route('admin.service-requests.store'), issue59AdminPayload($service, [
        'booking_mode' => 'period', 'preferred_date' => $date,
        'preferred_time_start' => '12:00', 'preferred_time_end' => '15:00',
    ]))->assertRedirect();
    $request = ServiceRequest::query()->sole();

    $this->actingAs($operations)->patch(route('admin.service-requests.update', $request), [
        'name' => 'Corrected only', 'phone' => '91111111', 'status' => 'contacted',
    ])->assertRedirect();

    $request->refresh();
    expect($request->name)->toBe('Corrected only')
        ->and($request->preferred_window)->toBe('12:00–15:00')
        ->and($request->preferred_period_starts_at)->not->toBeNull();
});

test('public and admin period transitions require a date and public seasonal manual mode is rejected', function (): void {
    $generic = issue59CrmService('oljeskift');
    $seasonal = issue59CrmService('issue-59-public-mode', ServiceRequestFlow::Seasonal);
    $operations = User::factory()->operations()->create();

    $this->post(route('storefront.requests.store', $generic->slug), [
        'submission_token' => (string) Str::uuid(), 'fulfilment' => 'workshop', 'booking_mode' => 'period',
        'preferred_time_start' => '09:00', 'preferred_time_end' => '11:00', 'name' => 'Missing date',
        'phone' => '90000000', 'consent' => '1',
    ])->assertSessionHasErrors('preferred_date');

    $date = now('Europe/Oslo')->addDays(2)->format('Y-m-d');
    $this->post(route('storefront.requests.store', $seasonal->slug), issue59SeasonalPayload($date, [
        'booking_mode' => 'manual', 'preferred_window' => 'Ettermiddag',
    ]))->assertSessionHasErrors('booking_mode');

    $existing = ServiceRequest::query()->create([
        'reference' => 'SR-CRM-MANUAL-PERIOD', 'locale' => 'nb', 'source' => ServiceRequestSource::AdminManual,
        'service_slug' => $generic->slug, 'fulfilment' => 'workshop', 'preferred_window' => 'Ettermiddag',
        'name' => 'Admin period transition', 'phone' => '90000000', 'phone_normalized' => '4790000000',
        'status' => ServiceRequestStatus::New,
    ]);
    $this->actingAs($operations)->put(route('admin.service-requests.update', $existing), issue59AdminPayload($generic, [
        'operational_form' => '1', 'booking_mode' => 'period', 'preferred_time_start' => '09:00',
        'preferred_time_end' => '11:00',
    ]))->assertSessionHasErrors('preferred_date');

    expect(ServiceRequest::query()->count())->toBe(1)
        ->and($existing->refresh()->preferred_period_starts_at)->toBeNull()
        ->and($existing->preferred_window)->toBe('Ettermiddag');
});

test('work views and district and dekkhotell filters combine and persist in pagination', function (): void {
    $service = issue59CrmService('issue-59-filter-service');
    $oil = issue59CrmService('oljeskift');
    $operations = User::factory()->operations()->create();

    foreach (range(1, 27) as $index) {
        ServiceRequest::query()->create([
            'reference' => sprintf('SR-CRM-%02d', $index), 'locale' => 'nb', 'source' => ServiceRequestSource::AdminManual,
            'created_by_user_id' => $operations->id, 'service_slug' => $index === 27 ? $oil->slug : $service->slug,
            'fulfilment' => $index === 26 ? 'workshop' : 'mobile', 'preferred_date' => now()->addDays(2),
            'district' => $index === 26 ? 'Sentrum' : 'Tromsdalen', 'dekkhotell' => $index !== 26,
            'additional_services' => $index === 25 ? ['air_filter'] : null,
            'name' => 'CRM Customer', 'phone' => '90000000', 'phone_normalized' => '4790000000',
            'status' => $index === 24 ? ServiceRequestStatus::Closed : ServiceRequestStatus::New,
        ]);
    }

    $response = $this->actingAs($operations)->get(route('admin.service-requests.index', [
        'view' => 'mobile', 'district' => 'Troms', 'dekkhotell' => '1', 'status' => 'new',
    ]))->assertOk()->assertSee('SR-CRM-01')->assertDontSee('SR-CRM-24')->assertDontSee('SR-CRM-26');
    $response->assertSee('view=mobile', false)->assertSee('district=Troms', false)->assertSee('dekkhotell=1', false);

    $this->actingAs($operations)->get(route('admin.service-requests.index', ['view' => 'additional']))
        ->assertOk()->assertSee('SR-CRM-25')->assertSee('SR-CRM-27')->assertDontSee('SR-CRM-01');
});

test('additional work view includes supported services and only nonempty add-on selections', function (): void {
    $operations = User::factory()->operations()->create();
    foreach (['bilbatteri', 'bildiagnose', 'lyspaerer-viskerblad', 'unrelated-service'] as $slug) {
        issue59CrmService($slug);
    }
    foreach ([
        ['SR-ADD-BATTERY', 'bilbatteri', null],
        ['SR-ADD-DIAG', 'bildiagnose', []],
        ['SR-ADD-WIPER', 'lyspaerer-viskerblad', null],
        ['SR-ADD-EMPTY', 'unrelated-service', []],
        ['SR-ADD-SELECTED', 'unrelated-service', ['air_filter']],
    ] as [$reference, $slug, $addons]) {
        ServiceRequest::query()->create([
            'reference' => $reference, 'locale' => 'nb', 'source' => ServiceRequestSource::AdminManual,
            'service_slug' => $slug, 'fulfilment' => 'workshop', 'additional_services' => $addons,
            'name' => 'Additional view', 'phone' => '90000000', 'phone_normalized' => '4790000000',
            'status' => ServiceRequestStatus::New,
        ]);
    }

    $this->actingAs($operations)->get(route('admin.service-requests.index', ['view' => 'additional']))
        ->assertOk()->assertSee('SR-ADD-BATTERY')->assertSee('SR-ADD-DIAG')->assertSee('SR-ADD-WIPER')
        ->assertSee('SR-ADD-SELECTED')->assertDontSee('SR-ADD-EMPTY');
});

test('manual van assignment is mobile-only filterable and survives contact edits', function (): void {
    $service = issue59CrmService('issue-59-mobile-van');
    $operations = User::factory()->operations()->create();
    $payload = issue59AdminPayload($service, [
        'operational_form' => '1', 'fulfilment' => 'mobile', 'address' => 'Vanvegen 1',
        'district' => 'Sentrum', 'assigned_van' => 'van_1',
    ]);

    $this->actingAs($operations)->post(route('admin.service-requests.store'), $payload)->assertRedirect();
    $request = ServiceRequest::query()->sole();
    expect($request->assigned_van)->toBe('van_1');

    $this->actingAs($operations)->patch(route('admin.service-requests.update', $request), [
        'name' => 'Contact only', 'phone' => '91111111', 'status' => 'contacted',
    ])->assertRedirect();
    expect($request->refresh()->assigned_van)->toBe('van_1');

    $this->actingAs($operations)->get(route('admin.service-requests.index', ['assigned_van' => 'van_1']))
        ->assertOk()->assertSee($request->reference);
    $this->actingAs($operations)->get(route('admin.service-requests.index', ['assigned_van' => 'van_2']))
        ->assertOk()->assertDontSee($request->reference);

    $this->actingAs($operations)->put(route('admin.service-requests.update', $request), issue59AdminPayload($service, [
        'operational_form' => '1', 'fulfilment' => 'workshop', 'name' => 'Contact only', 'phone' => '91111111',
    ]))->assertRedirect();
    expect($request->refresh()->assigned_van)->toBeNull();

    $this->actingAs($operations)->put(route('admin.service-requests.update', $request), issue59AdminPayload($service, [
        'operational_form' => '1', 'fulfilment' => 'workshop', 'assigned_van' => 'van_2',
    ]))->assertSessionHasErrors('assigned_van');
});

test('public requests cannot forge an internal van assignment', function (): void {
    $service = issue59CrmService('issue-59-public-van');
    $this->post(route('storefront.requests.store', $service->slug), [
        'submission_token' => (string) Str::uuid(), 'fulfilment' => 'mobile', 'address' => 'Publicvegen 1',
        'assigned_van' => 'van_2', 'name' => 'Public van attempt', 'phone' => '90000000', 'consent' => '1',
    ])->assertRedirect();

    expect(ServiceRequest::query()->sole()->assigned_van)->toBeNull();
});

function issue59CrmService(string $slug, ServiceRequestFlow $flow = ServiceRequestFlow::Generic): ServicePage
{
    return ServicePage::query()->create([
        'slug' => $slug, 'sort_order' => 1, 'publication_status' => ServicePagePublicationStatus::Published,
        'request_flow' => $flow, 'title_nb' => "NB {$slug}", 'title_ru' => "RU {$slug}",
    ]);
}

/** @return array<string, mixed> */
function issue59SeasonalPayload(string $date, array $override = []): array
{
    return array_replace([
        'submission_token' => (string) Str::uuid(), 'fulfilment' => 'workshop', 'preferred_date' => $date,
        'car_count' => 1, 'vehicle' => 'Tesla Model Y', 'vehicle_type' => 'passenger', 'rim_band' => 'r19',
        'storage_plan' => 'none', 'name' => 'Public CRM Customer', 'email' => 'public@example.test',
        'phone' => '90000000', 'consent' => '1',
    ], $override);
}

/** @return array<string, mixed> */
function issue59AdminPayload(ServicePage $service, array $override = []): array
{
    return array_replace([
        'submission_token' => (string) Str::uuid(), 'service_slug' => $service->slug,
        'fulfilment' => 'workshop', 'booking_mode' => 'manual', 'name' => 'Admin CRM Customer',
        'email' => null, 'phone' => '90000000',
    ], $override);
}
