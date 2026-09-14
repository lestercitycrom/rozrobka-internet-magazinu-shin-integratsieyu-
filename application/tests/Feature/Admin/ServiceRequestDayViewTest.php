<?php

use App\Models\User;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Enums\ServiceRequestFlow;
use App\Modules\Content\Models\ServicePage;
use App\Modules\ServiceBooking\Enums\ServiceRequestSource;
use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

test('operations day view is restricted to owner and operations', function (): void {
    issue66FreezeAtOsloDay();
    $owner = User::factory()->owner()->create();
    $operations = User::factory()->operations()->create();
    $editor = User::factory()->editor()->create();
    $customer = User::factory()->customer()->create();
    $inactive = User::factory()->operations()->inactive()->create();

    $this->get(route('admin.service-requests.day'))
        ->assertRedirect(route('admin.login'));
    $this->actingAs($owner)->get(route('admin.service-requests.day'))->assertOk();
    $this->actingAs($operations)->get(route('admin.service-requests.day'))->assertOk();
    $this->actingAs($editor)->get(route('admin.service-requests.day'))->assertForbidden();
    $this->actingAs($customer)->get(route('admin.service-requests.day'))->assertForbidden();
    $this->actingAs($inactive)->get(route('admin.service-requests.day'))->assertForbidden();
});

test('day view uses the Oslo date orders structured work chronologically and isolates cancelled other dates and resources', function (): void {
    issue66FreezeAtOsloDay();
    $service = issue66DayService();
    $operations = User::factory()->operations()->create();

    issue66DayRequest($service, 'SR-DAY-MANUAL-66', [
        'preferred_date' => '2026-01-16',
        'preferred_window' => 'Ettermiddag',
        'fulfilment' => 'mobile',
        'assigned_van' => 'van_2',
        'created_at' => '2026-01-10 07:00:00',
    ]);
    issue66DayRequest($service, 'SR-DAY-EXACT-66', [
        'preferred_date' => '2026-01-16',
        'preferred_window' => '09:00–09:30',
        'booking_slot_key' => 'b1-66-20260116-0900',
        'booking_starts_at' => '2026-01-16 08:00:00',
        'booking_ends_at' => '2026-01-16 08:30:00',
        'fulfilment' => 'workshop',
        'created_at' => '2026-01-12 07:00:00',
    ]);
    issue66DayRequest($service, 'SR-DAY-PERIOD-66', [
        'preferred_date' => '2026-01-16',
        'preferred_window' => '08:30–09:00',
        'preferred_period_starts_at' => '2026-01-16 07:30:00',
        'preferred_period_ends_at' => '2026-01-16 08:00:00',
        'fulfilment' => 'mobile',
        'assigned_van' => 'van_1',
        'created_at' => '2026-01-14 07:00:00',
    ]);
    issue66DayRequest($service, 'SR-DAY-UNASSIGNED-66', [
        'preferred_date' => '2026-01-16',
        'preferred_window' => 'Ring før oppmøte',
        'fulfilment' => 'mobile',
        'assigned_van' => null,
    ]);
    issue66DayRequest($service, 'SR-DAY-CANCELLED-66', [
        'preferred_date' => '2026-01-16',
        'booking_slot_key' => 'b1-66-20260116-0800',
        'booking_starts_at' => '2026-01-16 07:00:00',
        'booking_ends_at' => '2026-01-16 07:30:00',
        'status' => ServiceRequestStatus::Cancelled,
    ]);
    issue66DayRequest($service, 'SR-DAY-OTHER-DATE-66', [
        'preferred_date' => '2026-01-17',
        'booking_slot_key' => 'b1-66-20260117-0800',
        'booking_starts_at' => '2026-01-17 07:00:00',
        'booking_ends_at' => '2026-01-17 07:30:00',
    ]);

    $this->actingAs($operations)
        ->get(route('admin.service-requests.day'))
        ->assertOk()
        ->assertSee('value="2026-01-16"', false)
        ->assertSeeInOrder(['SR-DAY-PERIOD-66', 'SR-DAY-EXACT-66', 'SR-DAY-MANUAL-66'])
        ->assertSee('Ønsket tidsrom')
        ->assertSee('Bekreftet tid')
        ->assertSee('Ikke bekreftet')
        ->assertDontSee('SR-DAY-CANCELLED-66')
        ->assertDontSee('SR-DAY-OTHER-DATE-66');

    $this->actingAs($operations)
        ->get(route('admin.service-requests.day', ['date' => '2026-01-17']))
        ->assertOk()
        ->assertSee('SR-DAY-OTHER-DATE-66')
        ->assertDontSee('SR-DAY-EXACT-66');

    $this->actingAs($operations)
        ->get(route('admin.service-requests.day', ['resource' => 'workshop']))
        ->assertOk()
        ->assertSee('SR-DAY-EXACT-66')
        ->assertDontSee('SR-DAY-PERIOD-66')
        ->assertDontSee('SR-DAY-MANUAL-66')
        ->assertDontSee('SR-DAY-UNASSIGNED-66');
    $this->actingAs($operations)
        ->get(route('admin.service-requests.day', ['resource' => 'van_1']))
        ->assertOk()
        ->assertSee('SR-DAY-PERIOD-66')
        ->assertDontSee('SR-DAY-EXACT-66')
        ->assertDontSee('SR-DAY-MANUAL-66')
        ->assertDontSee('SR-DAY-UNASSIGNED-66');
    $this->actingAs($operations)
        ->get(route('admin.service-requests.day', ['resource' => 'van_2']))
        ->assertOk()
        ->assertSee('SR-DAY-MANUAL-66')
        ->assertDontSee('SR-DAY-EXACT-66')
        ->assertDontSee('SR-DAY-PERIOD-66')
        ->assertDontSee('SR-DAY-UNASSIGNED-66');
    $this->actingAs($operations)
        ->get(route('admin.service-requests.day', ['resource' => 'mobile_unassigned']))
        ->assertOk()
        ->assertSee('SR-DAY-UNASSIGNED-66')
        ->assertDontSee('SR-DAY-EXACT-66')
        ->assertDontSee('SR-DAY-PERIOD-66')
        ->assertDontSee('SR-DAY-MANUAL-66');

    $this->actingAs($operations)
        ->get(route('admin.service-requests.day', ['include_cancelled' => 1]))
        ->assertOk()
        ->assertSee('SR-DAY-CANCELLED-66');
});

test('day view localizes operational storage preparation for all vehicles and omits unnecessary PII', function (): void {
    issue66FreezeAtOsloDay();
    $service = issue66DayService();
    $norwegian = User::factory()->operations()->create(['locale' => 'nb']);
    $russian = User::factory()->operations()->russian()->create();

    issue66DayRequest($service, 'SR-DAY-STORAGE-66', [
        'preferred_date' => '2026-01-16',
        'preferred_window' => '10:00–11:00',
        'preferred_period_starts_at' => '2026-01-16 09:00:00',
        'preferred_period_ends_at' => '2026-01-16 10:00:00',
        'name' => 'Operational day customer',
        'phone' => '95555555',
        'phone_normalized' => '4795555555',
        'email' => 'must-not-leak-on-day@example.test',
        'address' => 'Syntheticvegen 66',
        'district' => 'Tromsdalen',
        'note' => 'CUSTOMER NOTE MUST NOT LEAK ON DAY VIEW',
        'admin_note' => 'ADMIN NOTE MUST NOT LEAK ON DAY VIEW',
        'vehicles' => [
            issue66DayVehicle('First operations car', 'AA66001', 'none'),
            issue66DayVehicle('Second operations car', 'BB66002', 'renew', 'KIT-77'),
            issue66DayVehicle('Third operations car', 'CC66003', 'last', '00042'),
        ],
    ]);

    $norwegianResponse = $this->actingAs($norwegian)
        ->get(route('admin.service-requests.day'))
        ->assertOk()
        ->assertSee('lang="nb"', false)
        ->assertSee('Arbeidsdag')
        ->assertSee('Arbeidsdag service')
        ->assertSee('First operations car')
        ->assertSee('Second operations car')
        ->assertSee('Third operations car')
        ->assertSee('Hent lagret hjulsett og lagre det avmonterte settet videre')
        ->assertSee('Hent lagret hjulsett; det avmonterte settet skal ikke lagres på nytt')
        ->assertSee('KIT-77')
        ->assertSee('00042')
        ->assertDontSee('must-not-leak-on-day@example.test')
        ->assertDontSee('CUSTOMER NOTE MUST NOT LEAK ON DAY VIEW')
        ->assertDontSee('ADMIN NOTE MUST NOT LEAK ON DAY VIEW');
    expect(substr_count($norwegianResponse->getContent(), 'data-day-vehicle'))
        ->toBeGreaterThanOrEqual(3);

    $this->actingAs($russian)
        ->get(route('admin.service-requests.day'))
        ->assertOk()
        ->assertSee('lang="ru"', false)
        ->assertSee('Рабочий день')
        ->assertSee('Услуга рабочего дня')
        ->assertSee('Выдать хранящийся комплект и снова принять снятый комплект')
        ->assertSee('Выдать хранящийся комплект и не принимать снятый комплект обратно')
        ->assertSee('00042')
        ->assertDontSee('must-not-leak-on-day@example.test')
        ->assertDontSee('CUSTOMER NOTE MUST NOT LEAK ON DAY VIEW')
        ->assertDontSee('ADMIN NOTE MUST NOT LEAK ON DAY VIEW');
});

function issue66FreezeAtOsloDay(): void
{
    $now = CarbonImmutable::parse('2026-01-15 23:30:00', 'UTC');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);
}

function issue66DayService(): ServicePage
{
    return ServicePage::query()->create([
        'slug' => 'issue-66-day-service',
        'sort_order' => 1,
        'publication_status' => ServicePagePublicationStatus::Published,
        'request_flow' => ServiceRequestFlow::Seasonal,
        'title_nb' => 'Arbeidsdag service',
        'title_ru' => 'Услуга рабочего дня',
    ]);
}

/** @param array<string, mixed> $override */
function issue66DayRequest(ServicePage $service, string $reference, array $override = []): ServiceRequest
{
    $attributes = array_replace([
        'reference' => $reference,
        'locale' => 'nb',
        'source' => ServiceRequestSource::AdminManual,
        'service_slug' => $service->slug,
        'fulfilment' => 'workshop',
        'assigned_van' => null,
        'preferred_date' => null,
        'preferred_window' => null,
        'name' => 'Day request customer',
        'email' => null,
        'phone' => '90000000',
        'phone_normalized' => '4790000000',
        'address' => null,
        'district' => null,
        'note' => null,
        'admin_note' => null,
        'status' => ServiceRequestStatus::New,
    ], $override);

    $request = new ServiceRequest;
    $request->forceFill($attributes);
    $request->save();

    return $request;
}

/** @return array<string, string|null> */
function issue66DayVehicle(
    string $vehicle,
    string $registrationNumber,
    string $storagePlan,
    ?string $storageNumber = null,
): array {
    return [
        'vehicle' => $vehicle,
        'registration_number' => $registrationNumber,
        'tire_size' => '205/55 R16',
        'vehicle_type' => 'passenger',
        'rim_band' => 'r16-r18',
        'storage_plan' => $storagePlan,
        'storage_number' => $storageNumber,
    ];
}
