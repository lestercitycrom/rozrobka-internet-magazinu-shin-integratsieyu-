<?php

use App\Models\User;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Enums\ServiceRequestFlow;
use App\Modules\Content\Models\ServicePage;
use App\Modules\ServiceBooking\Enums\ServiceRequestSource;
use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use App\Modules\ServiceBooking\Models\Client;
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
    issue66DayRequest($service, 'SR-DAY-CLOSED-66', [
        'preferred_date' => '2026-01-16',
        'booking_slot_key' => 'b1-66-20260116-0730',
        'booking_starts_at' => '2026-01-16 06:30:00',
        'booking_ends_at' => '2026-01-16 07:00:00',
        'status' => ServiceRequestStatus::Closed,
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
        ->assertDontSee('SR-DAY-CLOSED-66')
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
        ->get(route('admin.service-requests.day', ['include_history' => 1]))
        ->assertOk()
        ->assertSee('SR-DAY-CANCELLED-66')
        ->assertSee('SR-DAY-CLOSED-66');
});

test('day view has a direct navigation entry Oslo today resource shortcuts and an undated queue', function (): void {
    issue66FreezeAtOsloDay();
    $service = issue66DayService();
    $operations = User::factory()->operations()->create();
    issue66DayRequest($service, 'SR-DAY-UNDATED-66');
    issue66DayRequest($service, 'SR-DAY-DATED-66', ['preferred_date' => '2026-01-16']);

    $day = $this->actingAs($operations)
        ->get(route('admin.service-requests.day', ['date' => '2026-01-16', 'resource' => 'van_1']))
        ->assertOk()
        ->assertSee('data-testid="admin-nav-operations_day"', false)
        ->assertSee('data-testid="operations-day-today"', false)
        ->assertSee('I dag i Europe/Oslo: 2026-01-16')
        ->assertSee(route('admin.service-requests.index', ['without_date' => 1]), false);

    foreach (['all', 'workshop', 'van_1', 'van_2', 'mobile_unassigned'] as $resource) {
        $day->assertSee('data-day-resource="'.$resource.'"', false);
    }

    $this->actingAs($operations)
        ->get(route('admin.service-requests.index', ['without_date' => 1]))
        ->assertOk()
        ->assertSee('SR-DAY-UNDATED-66')
        ->assertDontSee('SR-DAY-DATED-66')
        ->assertSee('name="without_date" value="1" checked', false);
});

test('preparation summary separates confirmed and pending vehicle actions and never promotes history', function (): void {
    issue66FreezeAtOsloDay();
    $service = issue66DayService();
    $norwegian = User::factory()->operations()->create(['locale' => 'nb']);
    $russian = User::factory()->operations()->russian()->create();

    issue66DayRequest($service, 'SR-DAY-PREP-CONFIRMED-66', [
        'preferred_date' => '2026-01-16',
        'booking_slot_key' => 'b1-66-20260116-1000',
        'booking_starts_at' => '2026-01-16 09:00:00',
        'booking_ends_at' => '2026-01-16 09:30:00',
        'vehicles' => [
            issue66DayVehicle('Confirmed renew car', 'PREP6601', 'renew', 'KIT-6601'),
            issue66DayVehicle('Confirmed LAST car', 'PREP6602', 'last'),
        ],
    ]);
    issue66DayRequest($service, 'SR-DAY-PREP-PENDING-GENERAL-66', [
        'preferred_date' => '2026-01-16',
        'preferred_window' => 'Ring kunden',
        'status' => ServiceRequestStatus::Contacted,
        'vehicle' => 'General storage car',
        'registration_number' => 'PREP6603',
        'dekkhotell' => false,
        'dekkhotell_already_stored' => true,
        'dekkhotell_number' => null,
    ]);
    issue66DayRequest($service, 'SR-DAY-PREP-CLOSED-66', [
        'preferred_date' => '2026-01-16',
        'status' => ServiceRequestStatus::Closed,
        'vehicles' => [issue66DayVehicle('Closed renew car', 'PREP6698', 'renew', 'CLOSED-KIT-66')],
    ]);
    issue66DayRequest($service, 'SR-DAY-PREP-CANCELLED-66', [
        'preferred_date' => '2026-01-16',
        'status' => ServiceRequestStatus::Cancelled,
        'vehicles' => [issue66DayVehicle('Cancelled LAST car', 'PREP6699', 'last', 'CANCELLED-KIT-66')],
    ]);

    $response = $this->actingAs($norwegian)
        ->get(route('admin.service-requests.day', [
            'date' => '2026-01-16',
            'resource' => 'all',
            'include_history' => 1,
        ]))
        ->assertOk();
    $html = $response->getContent();
    $confirmed = issue66DayElementText($html, 'section', 'data-preparation-group="confirmed"');
    $pending = issue66DayElementText($html, 'section', 'data-preparation-group="pending"');
    $summary = $confirmed.' '.$pending;
    $closed = issue66DayElementText($html, 'article', 'data-status="closed"');
    $cancelled = issue66DayElementText($html, 'article', 'data-status="cancelled"');

    expect($confirmed)
        ->toContain('SR-DAY-PREP-CONFIRMED-66')
        ->toContain('KIT-6601')
        ->toContain('Avklar lagernummer før forberedelse.')
        ->not->toContain('SR-DAY-PREP-PENDING-GENERAL-66')
        ->and($pending)
        ->toContain('SR-DAY-PREP-PENDING-GENERAL-66')
        ->toContain('General storage car')
        ->toContain('Avklar lagernummer før forberedelse.')
        ->not->toContain('SR-DAY-PREP-CONFIRMED-66')
        ->and($summary)
        ->not->toContain('SR-DAY-PREP-CLOSED-66')
        ->not->toContain('SR-DAY-PREP-CANCELLED-66')
        ->not->toContain('CLOSED-KIT-66')
        ->not->toContain('CANCELLED-KIT-66')
        ->and(substr_count($html, 'data-preparation-action'))->toBe(3)
        ->and($closed)->toContain('Historikk – ingen handling eller forberedelse skal utføres.')
        ->not->toContain('Ta med lagret hjulsett')
        ->and($cancelled)->toContain('Historikk – ingen handling eller forberedelse skal utføres.')
        ->not->toContain('LAST: Lever ut lagret hjulsett');

    $this->actingAs($russian)
        ->get(route('admin.service-requests.day', ['date' => '2026-01-16']))
        ->assertOk()
        ->assertSee('Подтверждённые')
        ->assertSee('Ожидают подтверждения')
        ->assertSee('Уточнить номер перед подготовкой.');
});

test('safe day return context survives show edit validation and update without changing the client snapshot', function (): void {
    issue66FreezeAtOsloDay();
    $service = issue66DayService();
    $operations = User::factory()->operations()->create();
    $client = Client::query()->create([
        'name' => 'Current directory client',
        'phone' => '98888888',
        'phone_normalized' => '4798888888',
        'email' => 'directory@example.test',
        'created_by_user_id' => $operations->id,
    ]);
    $item = issue66DayRequest($service, 'SR-DAY-RETURN-66', [
        'client_id' => $client->id,
        'preferred_date' => '2026-01-16',
        'fulfilment' => 'mobile',
        'assigned_van' => 'van_1',
        'name' => 'Historical request snapshot',
        'email' => 'snapshot@example.test',
        'phone' => '97777777',
        'phone_normalized' => '4797777777',
    ]);
    $returnTo = route('admin.service-requests.day', [
        'date' => '2026-01-16',
        'resource' => 'van_1',
        'include_history' => 1,
    ], false);
    $showUrl = route('admin.service-requests.show', ['request' => $item, 'return_to' => $returnTo]);
    $editUrl = route('admin.service-requests.edit', ['request' => $item, 'return_to' => $returnTo]);
    $updateUrl = route('admin.service-requests.update', ['request' => $item, 'return_to' => $returnTo]);

    $this->actingAs($operations)
        ->get(route('admin.service-requests.day', ['date' => '2026-01-16', 'resource' => 'van_1', 'include_history' => 1]))
        ->assertOk()
        ->assertSee($showUrl, false);
    $this->actingAs($operations)->get($showUrl)
        ->assertOk()
        ->assertSee('href="'.e($returnTo).'"', false)
        ->assertSee($editUrl, false);
    $this->actingAs($operations)->get($editUrl)
        ->assertOk()
        ->assertSee('action="'.$updateUrl.'"', false)
        ->assertSee('href="'.$showUrl.'"', false);

    $this->actingAs($operations)
        ->from($editUrl)
        ->put($updateUrl, ['status' => 'not-a-status'])
        ->assertRedirect($editUrl)
        ->assertSessionHasErrors('status');
    $this->actingAs($operations)->get($editUrl)
        ->assertOk()
        ->assertSee('action="'.$updateUrl.'"', false);

    $this->actingAs($operations)
        ->put($updateUrl, ['status' => 'contacted'])
        ->assertRedirect($showUrl);
    $item->refresh();
    expect($item->status)->toBe(ServiceRequestStatus::Contacted)
        ->and($item->client_id)->toBe($client->id)
        ->and($item->name)->toBe('Historical request snapshot')
        ->and($item->email)->toBe('snapshot@example.test')
        ->and($item->phone)->toBe('97777777')
        ->and($client->refresh()->name)->toBe('Current directory client')
        ->and($client->phone)->toBe('98888888');

    foreach (['https://evil.example/steal', '//evil.example/steal'] as $unsafeReturn) {
        $unsafeShowUrl = route('admin.service-requests.show', ['request' => $item, 'return_to' => $unsafeReturn]);
        $this->actingAs($operations)->get($unsafeShowUrl)
            ->assertOk()
            ->assertSee('href="'.route('admin.service-requests.index').'"', false)
            ->assertSee('href="'.route('admin.service-requests.edit', $item).'"', false)
            ->assertDontSee('href="'.e($unsafeReturn).'"', false);
    }
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

function issue66DayElementText(string $html, string $tag, string $attribute): string
{
    $matched = preg_match(
        '/<'.preg_quote($tag, '/').'\b[^>]*'.preg_quote($attribute, '/').'[^>]*>(.*?)<\/'.preg_quote($tag, '/').'>/su',
        $html,
        $matches,
    );
    expect($matched)->toBe(1);

    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5)) ?? '');
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
