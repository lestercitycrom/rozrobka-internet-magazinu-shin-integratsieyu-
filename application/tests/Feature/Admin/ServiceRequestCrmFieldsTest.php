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
        'rim_band' => 'r19', 'storage_plan' => 'none', 'registration_number' => null,
    ]))->assertRedirect();

    $request->refresh();
    expect($request->dekkhotell)->toBeFalse()
        ->and($request->vehicles[0]['storage_plan'])->toBe('none')
        ->and($request->vehicles[0]['registration_number'])->toBeNull();
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
