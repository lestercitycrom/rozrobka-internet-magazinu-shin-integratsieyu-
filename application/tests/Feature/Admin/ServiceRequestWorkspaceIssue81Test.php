<?php

use App\Models\User;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Models\ServicePage;
use App\Modules\ServiceBooking\Enums\ServiceRequestSource;
use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use App\Modules\ServiceBooking\Models\Client;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use App\Modules\ServiceBooking\Models\ServiceRequestEvent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('directory search covers operational identifiers and keeps storage matches on the same vehicle', function (): void {
    $service = issue81Service();
    $staff = User::factory()->operations()->create();
    $matched = issue81Request($service, [
        'reference' => 'SR-81-LITERAL%_',
        'name' => 'Kari Nordmann',
        'email' => 'kari@example.test',
        'phone' => '+47 912 34 567',
        'phone_normalized' => '4791234567',
        'vehicles' => [
            ['vehicle' => 'Tesla', 'registration_number' => 'NEW8101', 'tire_size' => '255/45 R19', 'storage_plan' => 'new', 'storage_number' => '000-NEW'],
            ['vehicle' => 'Volvo', 'registration_number' => 'RENEW8102', 'tire_size' => '235/55 R18', 'storage_plan' => 'renew', 'storage_number' => '000-RENEW'],
            ['vehicle' => 'Toyota', 'registration_number' => 'NONE8103', 'tire_size' => '205/55 R16', 'storage_plan' => 'none', 'storage_number' => null],
            ['vehicle' => 'Volkswagen', 'registration_number' => 'NEW8104', 'tire_size' => '215/55 R17', 'storage_plan' => 'new', 'storage_number' => '000-NEW-4'],
            ['vehicle' => 'Skoda', 'registration_number' => 'FIFTH8105', 'tire_size' => '225/45 R18', 'storage_plan' => 'last', 'storage_number' => '000-LAST-FIFTH'],
        ],
        'vehicle' => 'Tesla',
        'registration_number' => 'NEW8101',
        'dekkhotell' => true,
    ]);
    issue81Request($service, ['reference' => 'SR-81-LITERALXY', 'name' => 'Other customer']);

    foreach (['Kari Nordmann', 'kari@example.test', '912 34 567', 'RENEW8102', '000-RENEW'] as $needle) {
        $this->actingAs($staff)->get(route('admin.service-requests.index', ['q' => $needle]))
            ->assertOk()
            ->assertSee($matched->reference);
    }

    $this->actingAs($staff)->get(route('admin.service-requests.index', ['q' => 'LITERAL%_']))
        ->assertOk()
        ->assertSee('SR-81-LITERAL%_')
        ->assertDontSee('SR-81-LITERALXY');

    $this->actingAs($staff)->get(route('admin.service-requests.index', ['q' => 'NEW8101', 'storage_operation' => 'renew']))
        ->assertOk()
        ->assertDontSee($matched->reference);
    $this->actingAs($staff)->get(route('admin.service-requests.index', ['q' => 'RENEW8102', 'storage_operation' => 'renew']))
        ->assertOk()
        ->assertSee($matched->reference)
        ->assertSee('Treff på registreringsnummer for bil 2');
    $this->actingAs($staff)->get(route('admin.service-requests.index', ['q' => '810', 'storage_operation' => 'renew']))
        ->assertOk()
        ->assertSee('Treff på registreringsnummer for bil 2')
        ->assertDontSee('Treff på registreringsnummer for bil 1');
    $this->actingAs($staff)->get(route('admin.service-requests.index', ['q' => 'FIFTH8105', 'storage_operation' => 'last']))
        ->assertOk()
        ->assertSee('Treff på registreringsnummer for bil 5')
        ->assertSee('Biler: 5')
        ->assertSee('Tid ikke avtalt');
    $this->actingAs($staff)->get(route('admin.service-requests.index', ['q' => '000-LAST-FIFTH', 'storage_operation' => 'last']))
        ->assertOk()
        ->assertSee('Treff på dekkhotellnummer for bil 5');

    $staff->forceFill(['locale' => 'ru'])->save();
    $this->actingAs($staff)->get(route('admin.service-requests.index', ['q' => 'RENEW8102', 'storage_operation' => 'renew']))
        ->assertOk()
        ->assertSee('RENEW · продолжить хранение')
        ->assertSee('Совпадение по госномеру автомобиля 2')
        ->assertDontSee('service_requests.storage_operation_renew');

    issue81Request($service, [
        'reference' => 'SR-81-CONFIRMED',
        'booking_starts_at' => '2026-09-24 08:00:00',
        'booking_ends_at' => '2026-09-24 08:30:00',
    ]);
    issue81Request($service, [
        'reference' => 'SR-81-PERIOD',
        'preferred_period_starts_at' => '2026-09-25 10:00:00',
        'preferred_period_ends_at' => '2026-09-25 12:00:00',
    ]);
    $staff->forceFill(['locale' => 'nb'])->save();
    $this->actingAs($staff)->get(route('admin.service-requests.index', ['q' => 'SR-81-CONFIRMED']))
        ->assertOk()->assertSee('Bekreftet:');
    $this->actingAs($staff)->get(route('admin.service-requests.index', ['q' => 'SR-81-PERIOD']))
        ->assertOk()->assertSee('Ønsket periode:');
});

test('quick actions are versioned idempotent audited and role protected', function (): void {
    $service = issue81Service();
    $operations = User::factory()->operations()->create();
    $editor = User::factory()->editor()->create();
    $request = issue81Request($service, [
        'fulfilment' => 'mobile',
        'vehicles' => [[
            'vehicle' => 'Tesla', 'registration_number' => 'EV81001', 'tire_size' => '255/45 R19',
            'storage_plan' => 'renew', 'storage_number' => null,
        ]],
        'vehicle' => 'Tesla',
        'registration_number' => 'EV81001',
        'dekkhotell' => true,
    ])->fresh();
    $statusCommand = (string) Str::uuid();

    $payload = [
        'command' => 'status', 'command_id' => $statusCommand,
        'expected_version' => 1, 'status' => 'contacted',
    ];
    $this->actingAs($operations)->patch(route('admin.service-requests.quick-action', $request), $payload)
        ->assertSessionHasNoErrors()->assertRedirect();
    $this->actingAs($operations)->patch(route('admin.service-requests.quick-action', $request), $payload)
        ->assertSessionHasNoErrors()->assertRedirect();

    $request->refresh();
    expect($request->status)->toBe(ServiceRequestStatus::Contacted)
        ->and($request->operation_version)->toBe(2)
        ->and(ServiceRequestEvent::query()->where('command_id', $statusCommand)->count())->toBe(1)
        ->and(ServiceRequestEvent::query()->where('command_id', $statusCommand)->value('actor_user_id'))->toBe($operations->id);

    $this->actingAs($operations)->patch(route('admin.service-requests.quick-action', $request), [
        'command' => 'admin_note', 'command_id' => (string) Str::uuid(),
        'expected_version' => 1, 'admin_note' => 'Must not overwrite newer data',
    ])->assertSessionHasErrors('expected_version');
    expect($request->fresh()->admin_note)->toBeNull();

    $this->actingAs($operations)->patch(route('admin.service-requests.quick-action', $request), [
        'command' => 'assigned_van', 'command_id' => (string) Str::uuid(),
        'expected_version' => 2, 'assigned_van' => 'van_2',
    ])->assertSessionHasNoErrors();
    $this->actingAs($operations)->patch(route('admin.service-requests.quick-action', $request), [
        'command' => 'storage_number', 'command_id' => (string) Str::uuid(),
        'expected_version' => 3, 'vehicle_index' => 0, 'storage_number' => '00042-A',
    ])->assertSessionHasNoErrors();

    $request->refresh();
    expect($request->assigned_van)->toBe('van_2')
        ->and($request->vehicles[0]['storage_number'])->toBe('00042-A')
        ->and($request->operation_version)->toBe(4);

    $noteCommand = (string) Str::uuid();
    $this->actingAs($operations)->patch(route('admin.service-requests.quick-action', $request), [
        'command' => 'admin_note', 'command_id' => $noteCommand,
        'expected_version' => 4, 'admin_note' => 'Private customer context',
    ])->assertSessionHasNoErrors();
    $noteAudit = ServiceRequestEvent::query()->where('command_id', $noteCommand)->sole();
    expect($noteAudit->changes)->toBe(['admin_note' => ['changed' => true]])
        ->and(json_encode($noteAudit->changes))->not->toContain('Private customer context');

    $history = $this->view('admin.requests.partials.history', [
        'request' => $request->fresh()->load('events.actor'),
    ]);
    $history->assertSee('Ny → Kontaktet')
        ->assertSee('tom → 00042-A')
        ->assertSee('bil 1')
        ->assertSee('intern merknad:')
        ->assertSee('endret (verdien vises ikke)')
        ->assertDontSee('Private customer context');

    $this->actingAs($editor)->patch(route('admin.service-requests.quick-action', $request), [
        'command' => 'status', 'command_id' => (string) Str::uuid(),
        'expected_version' => 5, 'status' => 'closed',
    ])->assertForbidden();
    expect($request->fresh()->status)->toBe(ServiceRequestStatus::Contacted);
});

test('stale quick and full edits require an explicit reviewed version before retry', function (): void {
    $service = issue81Service();
    $staff = User::factory()->operations()->create();
    $request = issue81Request($service, ['admin_note' => 'Original note'])->fresh();

    $this->actingAs($staff)->patch(route('admin.service-requests.quick-action', $request), [
        'command' => 'admin_note', 'command_id' => (string) Str::uuid(),
        'expected_version' => 1, 'admin_note' => 'Saved in tab A',
    ])->assertSessionHasNoErrors();

    $workspace = route('admin.service-requests.index', ['selected' => $request->id]);
    $this->from($workspace)->actingAs($staff)->patch(route('admin.service-requests.quick-action', $request), [
        'command' => 'admin_note', 'command_id' => (string) Str::uuid(),
        'expected_version' => 1, 'admin_note' => 'Draft from tab B', 'return_to' => $workspace,
    ])->assertSessionHasErrors('expected_version')->assertRedirect($workspace);

    $this->actingAs($staff)->get($workspace)
        ->assertOk()
        ->assertSee('data-conflict-draft="true"', false)
        ->assertSee('name="expected_version" value="1"', false)
        ->assertSee('name="conflict_version" value="2"', false)
        ->assertSee('Saved in tab A')
        ->assertSee('Draft from tab B');

    $this->actingAs($staff)->patch(route('admin.service-requests.quick-action', $request), [
        'command' => 'admin_note', 'command_id' => (string) Str::uuid(),
        'expected_version' => 1, 'admin_note' => 'Silent retry must fail',
    ])->assertSessionHasErrors('expected_version');
    expect($request->fresh()->admin_note)->toBe('Saved in tab A');

    $this->actingAs($staff)->patch(route('admin.service-requests.quick-action', $request), [
        'command' => 'admin_note', 'command_id' => (string) Str::uuid(),
        'expected_version' => 1, 'resolve_conflict' => '1', 'conflict_version' => 2,
        'admin_note' => 'Reviewed retry from tab B',
    ])->assertSessionHasNoErrors();
    $resolved = $request->fresh();
    expect($resolved->admin_note)->toBe('Reviewed retry from tab B')
        ->and($resolved->operation_version)->toBe(3);

    $this->actingAs($staff)->patch(route('admin.service-requests.quick-action', $request), [
        'command' => 'admin_note', 'command_id' => (string) Str::uuid(),
        'expected_version' => 3, 'admin_note' => 'Saved in tab A again',
    ])->assertSessionHasNoErrors();

    $edit = route('admin.service-requests.edit', $request);
    $this->from($edit)->actingAs($staff)->patch(route('admin.service-requests.update', $request), [
        'expected_version' => 3, 'command_id' => (string) Str::uuid(), 'name' => 'Retained full edit',
    ])->assertSessionHasErrors('expected_version')->assertRedirect($edit);
    $this->actingAs($staff)->get($edit)
        ->assertOk()
        ->assertSee('data-conflict-draft="true"', false)
        ->assertSee('Saved in tab A again')
        ->assertSee('value="Retained full edit"', false)
        ->assertSee('name="expected_version" value="3"', false)
        ->assertSee('name="conflict_version" value="4"', false);

    $this->actingAs($staff)->patch(route('admin.service-requests.update', $request), [
        'expected_version' => 3, 'command_id' => (string) Str::uuid(), 'name' => 'Silent full retry',
    ])->assertSessionHasErrors('expected_version');
    expect($request->fresh()->name)->not->toBe('Silent full retry');

    $this->actingAs($staff)->patch(route('admin.service-requests.update', $request), [
        'expected_version' => 3, 'resolve_conflict' => '1', 'conflict_version' => 4,
        'command_id' => (string) Str::uuid(), 'name' => 'Reviewed full retry',
    ])->assertSessionHasNoErrors();
    expect($request->fresh()->name)->toBe('Reviewed full retry');
});

test('cancelled booked requests cannot be reactivated and list return context cannot escape the admin', function (): void {
    $service = issue81Service();
    $staff = User::factory()->operations()->create();
    $request = issue81Request($service, [
        'status' => ServiceRequestStatus::Cancelled,
        'booking_slot_key' => 'issue-81-booked',
        'booking_starts_at' => now()->addDay(),
        'booking_ends_at' => now()->addDay()->addMinutes(30),
    ])->fresh();

    $this->actingAs($staff)->patch(route('admin.service-requests.quick-action', $request), [
        'command' => 'status', 'command_id' => (string) Str::uuid(),
        'expected_version' => 1, 'status' => 'new',
    ])->assertSessionHasErrors('status');

    $listContext = route('admin.service-requests.index', [
        'q' => 'Outside filter', 'page' => 2, 'selected' => $request->id,
    ], false);
    $this->actingAs($staff)->get($listContext)
        ->assertOk()
        ->assertSee('Forespørselen er lagret, men samsvarer ikke lenger med det aktive filteret.');

    $response = $this->actingAs($staff)->patch(route('admin.service-requests.quick-action', $request), [
        'command' => 'admin_note', 'command_id' => (string) Str::uuid(),
        'expected_version' => 1, 'admin_note' => 'Context preserved', 'return_to' => $listContext,
    ])->assertSessionHasNoErrors();
    expect(parse_url($response->headers->get('Location'), PHP_URL_PATH))->toBe('/admin/service-requests');
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $redirectQuery);
    expect($redirectQuery)->toMatchArray(['q' => 'Outside filter', 'page' => '2', 'selected' => (string) $request->id]);

    $external = $this->actingAs($staff)->patch(route('admin.service-requests.quick-action', $request), [
        'command' => 'admin_note', 'command_id' => (string) Str::uuid(),
        'expected_version' => 2, 'admin_note' => 'Safe redirect', 'return_to' => 'https://evil.example/steal',
    ])->assertSessionHasNoErrors();
    expect(parse_url($external->headers->get('Location'), PHP_URL_HOST))->toBe('localhost')
        ->and(parse_url($external->headers->get('Location'), PHP_URL_PATH))->toBe('/admin/service-requests/'.$request->id);
});

test('client linking is explicit versioned idempotent and preserves the request snapshot', function (): void {
    $service = issue81Service();
    $staff = User::factory()->operations()->create();
    $request = issue81Request($service, [
        'name' => 'Original request name', 'phone' => '91234567',
        'phone_normalized' => '4791234567', 'address' => 'Original address',
    ])->fresh();

    $this->actingAs($staff)->post(route('admin.clients.store'), [
        'name' => 'Stale client', 'phone' => '92345678', 'link_request_id' => $request->id,
        'expected_version' => 99, 'command_id' => (string) Str::uuid(),
    ])->assertSessionHasErrors('expected_version');
    expect(Client::query()->count())->toBe(0);

    $commandId = (string) Str::uuid();
    $payload = [
        'name' => 'Linked client', 'phone' => '92345678', 'link_request_id' => $request->id,
        'expected_version' => 1, 'command_id' => $commandId,
    ];
    $this->actingAs($staff)->post(route('admin.clients.store'), $payload)->assertSessionHasNoErrors();
    $this->actingAs($staff)->post(route('admin.clients.store'), $payload)->assertSessionHasNoErrors();

    $request->refresh();
    expect(Client::query()->count())->toBe(1)
        ->and($request->client_id)->toBe(Client::query()->sole()->id)
        ->and($request->name)->toBe('Original request name')
        ->and($request->phone)->toBe('91234567')
        ->and($request->address)->toBe('Original address')
        ->and(ServiceRequestEvent::query()->where('command_id', $commandId)->count())->toBe(1);
});

test('stale create and link keeps the client draft and cannot replace a current link silently', function (): void {
    $service = issue81Service();
    $staff = User::factory()->operations()->create();
    $request = issue81Request($service)->fresh();
    $currentClient = Client::query()->create([
        'name' => 'Client selected in tab A', 'phone' => '91234567', 'phone_normalized' => '4791234567',
        'created_by_user_id' => $staff->id,
    ]);

    $this->actingAs($staff)->patch(route('admin.service-requests.update', $request), [
        'expected_version' => 1, 'command_id' => (string) Str::uuid(), 'client_id' => $currentClient->id,
    ])->assertSessionHasNoErrors();

    $create = route('admin.clients.create', ['service_request' => $request->id]);
    $payload = [
        'name' => 'Retained client draft', 'phone' => '92345678', 'link_request_id' => $request->id,
        'expected_version' => 1, 'command_id' => (string) Str::uuid(),
    ];
    $this->from($create)->actingAs($staff)->post(route('admin.clients.store'), $payload)
        ->assertSessionHasErrors('expected_version')->assertRedirect($create);
    expect(Client::query()->where('name', 'Retained client draft')->exists())->toBeFalse();

    $this->actingAs($staff)->get($create)
        ->assertOk()
        ->assertSee('data-conflict-draft="true"', false)
        ->assertSee('Client selected in tab A')
        ->assertSee('value="Retained client draft"', false)
        ->assertSee('name="expected_version" value="1"', false)
        ->assertSee('name="conflict_version" value="2"', false);

    $this->actingAs($staff)->post(route('admin.clients.store'), [
        ...$payload, 'command_id' => (string) Str::uuid(), 'name' => 'Silent replacement',
    ])->assertSessionHasErrors('expected_version');
    expect($request->fresh()->client_id)->toBe($currentClient->id)
        ->and(Client::query()->where('name', 'Silent replacement')->exists())->toBeFalse();

    $this->actingAs($staff)->post(route('admin.clients.store'), [
        ...$payload, 'command_id' => (string) Str::uuid(), 'resolve_conflict' => '1', 'conflict_version' => 2,
    ])->assertSessionHasNoErrors();
    expect($request->fresh()->client->name)->toBe('Retained client draft');
});

test('possible duplicate clients are warned about but never merged automatically', function (): void {
    $service = issue81Service();
    $staff = User::factory()->operations()->create();
    $existing = Client::query()->create([
        'name' => 'Existing family member',
        'phone' => '912 34 567',
        'phone_normalized' => '4791234567',
        'email' => 'family@example.test',
        'created_by_user_id' => $staff->id,
    ]);
    $request = issue81Request($service, [
        'name' => 'New family member',
        'phone' => '+47 912 34 567',
        'phone_normalized' => '4791234567',
        'email' => 'other@example.test',
    ])->fresh();

    $this->actingAs($staff)->get(route('admin.clients.create', ['service_request' => $request]))
        ->assertOk()
        ->assertSee('Mulig eksisterende kunde')
        ->assertSee($existing->name)
        ->assertSee('Ingenting slås sammen automatisk');

    $this->actingAs($staff)->post(route('admin.clients.store'), [
        'name' => 'New family member',
        'phone' => '+47 912 34 567',
        'email' => 'other@example.test',
        'link_request_id' => $request->id,
        'expected_version' => 1,
        'command_id' => (string) Str::uuid(),
    ])->assertSessionHasNoErrors();

    expect(Client::query()->count())->toBe(2)
        ->and($request->fresh()->client_id)->not->toBe($existing->id)
        ->and($existing->fresh()->name)->toBe('Existing family member');
});

test('request workspace exposes pii and mutations only to active owner and operations roles', function (): void {
    $service = issue81Service();
    $request = issue81Request($service)->fresh();
    $owner = User::factory()->owner()->create();
    $operations = User::factory()->operations()->create();
    $editor = User::factory()->editor()->create();
    $customer = User::factory()->customer()->create();
    $inactive = User::factory()->operations()->inactive()->create();

    foreach ([$owner, $operations] as $allowed) {
        $this->actingAs($allowed)->get(route('admin.service-requests.index'))->assertOk()->assertSee($request->phone);
        $this->actingAs($allowed)->get(route('admin.service-requests.show', $request))->assertOk()->assertSee($request->phone);
    }

    $this->actingAs($owner)->patch(route('admin.service-requests.quick-action', $request), [
        'command' => 'status', 'command_id' => (string) Str::uuid(),
        'expected_version' => 1, 'status' => 'contacted',
    ])->assertSessionHasNoErrors();
    $this->actingAs($operations)->patch(route('admin.service-requests.quick-action', $request), [
        'command' => 'status', 'command_id' => (string) Str::uuid(),
        'expected_version' => 2, 'status' => 'closed',
    ])->assertSessionHasNoErrors();

    foreach ([$editor, $customer, $inactive] as $denied) {
        $this->actingAs($denied)->get(route('admin.service-requests.index'))->assertForbidden();
        $this->actingAs($denied)->get(route('admin.service-requests.show', $request))->assertForbidden();
        $this->actingAs($denied)->patch(route('admin.service-requests.quick-action', $request), [
            'command' => 'status', 'command_id' => (string) Str::uuid(),
            'expected_version' => 3, 'status' => 'new',
        ])->assertForbidden();
    }

    auth()->logout();
    $this->get(route('admin.service-requests.index'))->assertRedirect(route('admin.login'));
    $this->patch(route('admin.service-requests.quick-action', $request), [
        'command' => 'status', 'command_id' => (string) Str::uuid(),
        'expected_version' => 3, 'status' => 'new',
    ])->assertRedirect(route('admin.login'));
    expect($request->fresh()->status)->toBe(ServiceRequestStatus::Closed);
});

test('work queues overlap deliberately and list query count stays bounded at accepted volume', function (): void {
    $service = issue81Service();
    $serviceSlugs = [$service->slug];
    foreach ([2, 3] as $number) {
        $serviceSlugs[] = ServicePage::query()->create([
            'slug' => 'issue-81-service-'.$number,
            'sort_order' => $number,
            'publication_status' => ServicePagePublicationStatus::Published,
            'title_nb' => 'Issue 81-tjeneste '.$number,
            'title_ru' => 'Услуга Issue 81 '.$number,
        ])->slug;
    }
    $staff = User::factory()->operations()->create();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-23 10:00:00', 'Europe/Oslo'));
    $overlap = issue81Request($service, [
        'reference' => 'SR-81-OVERLAP', 'fulfilment' => 'mobile', 'preferred_date' => '2026-09-23',
        'assigned_van' => null, 'vehicles' => [[
            'vehicle' => 'Volvo', 'registration_number' => 'QUEUE81', 'tire_size' => '235/55 R18',
            'storage_plan' => 'renew', 'storage_number' => null,
        ]],
    ]);

    foreach (['new', 'pending_time', 'unassigned_van', 'missing_kit', 'today'] as $queue) {
        $this->actingAs($staff)->get(route('admin.service-requests.index', ['queue' => $queue]))
            ->assertOk()->assertSee($overlap->reference);
    }

    $now = now();
    foreach (array_chunk(range(1, 1001), 200) as $chunk) {
        DB::table('clients')->insert(array_map(static function (int $index) use ($now): array {
            $phone = $index <= 2 ? '90000000' : '9'.str_pad((string) $index, 7, '0', STR_PAD_LEFT);

            return [
                'name' => sprintf('Volume client %04d', $index), 'phone' => $phone,
                'phone_normalized' => '47'.$phone,
                'email' => sprintf('volume-%04d@example.test', $index), 'created_at' => $now, 'updated_at' => $now,
            ];
        }, $chunk));
    }
    foreach (array_chunk(range(1, 499), 100) as $chunk) {
        DB::table('service_requests')->insert(array_map(static function (int $index) use ($now, $serviceSlugs): array {
            $vehicleCount = [1, 3, 5][$index % 3];
            $plans = ['none', 'new', 'renew', 'last'];
            $vehicles = array_map(static function (int $vehicleNumber) use ($index, $plans): array {
                $plan = $plans[($index + $vehicleNumber) % count($plans)];

                return [
                    'vehicle' => "Volume vehicle {$vehicleNumber}",
                    'registration_number' => sprintf('VOL%04d%02d', $index, $vehicleNumber),
                    'tire_size' => '205/55 R16',
                    'vehicle_type' => 'passenger',
                    'rim_band' => 'r16-r18',
                    'storage_plan' => $plan,
                    'storage_number' => in_array($plan, ['new', 'renew', 'last'], true) && $index % 4 !== 0
                        ? sprintf('KIT-%04d-%02d', $index, $vehicleNumber)
                        : null,
                ];
            }, range(1, $vehicleCount));
            $phone = $index <= 2 ? '90000000' : '8'.str_pad((string) $index, 7, '0', STR_PAD_LEFT);
            $legacyUnknown = $index === 499;

            return [
                'reference' => sprintf('SR-81-VOLUME-%04d', $index), 'submission_token' => (string) Str::uuid(),
                'locale' => $index % 5 === 0 ? 'ru' : 'nb', 'source' => 'admin_manual',
                'client_id' => $index <= 250 ? $index : null,
                'service_slug' => $serviceSlugs[$index % count($serviceSlugs)],
                'fulfilment' => $index % 2 === 0 ? 'mobile' : 'workshop',
                'assigned_van' => $index % 2 === 0 && $index % 3 !== 0 ? 'van_1' : null,
                'preferred_date' => $index <= 59 ? '2026-09-23' : null,
                'preferred_window' => $index % 3 === 0 ? 'Ettermiddag' : null,
                'vehicle' => $legacyUnknown ? 'Legacy unknown vehicle' : null,
                'registration_number' => $legacyUnknown ? 'LEGACY499' : null,
                'dekkhotell' => $legacyUnknown,
                'dekkhotell_already_stored' => null,
                'vehicles' => $legacyUnknown ? null : json_encode($vehicles, JSON_THROW_ON_ERROR),
                'name' => sprintf('Volume request %04d', $index),
                'phone' => $phone, 'phone_normalized' => '47'.$phone,
                'status' => ['new', 'contacted', 'closed', 'cancelled'][$index % 4],
                'operation_version' => 1, 'created_at' => $now, 'updated_at' => $now,
            ];
        }, $chunk));
    }

    expect(ServiceRequest::query()->count())->toBe(500)
        ->and(ServiceRequest::query()->whereDate('preferred_date', '2026-09-23')->count())->toBe(60)
        ->and(Client::query()->count())->toBe(1001)
        ->and(Client::query()->where('phone_normalized', '4790000000')->count())->toBe(2)
        ->and(ServiceRequest::query()->whereNull('client_id')->count())->toBeGreaterThan(0);
    $this->actingAs($staff)->get(route('admin.service-requests.index', ['storage_operation' => 'unknown']))
        ->assertOk()
        ->assertSee('SR-81-VOLUME-0499');

    $queries = 0;
    DB::listen(static function () use (&$queries): void {
        $queries++;
    });
    $startedAt = hrtime(true);
    $this->actingAs($staff)->get(route('admin.service-requests.index', ['q' => 'SR-81-VOLUME-0499']))
        ->assertOk()->assertSee('SR-81-VOLUME-0499');
    $durationMs = round((hrtime(true) - $startedAt) / 1_000_000, 2);
    expect($queries)->toBeLessThanOrEqual(15);
    if (getenv('ISSUE81_REPORT_METRICS') === '1') {
        fwrite(STDOUT, PHP_EOL.json_encode([
            'issue' => 81,
            'clients' => 1001,
            'requests' => 500,
            'queries' => $queries,
            'request_ms' => $durationMs,
        ], JSON_THROW_ON_ERROR).PHP_EOL);
    }
    CarbonImmutable::setTestNow();
});

function issue81Service(): ServicePage
{
    return ServicePage::query()->create([
        'slug' => 'issue-81-service', 'sort_order' => 1,
        'publication_status' => ServicePagePublicationStatus::Published,
        'title_nb' => 'Issue 81-tjeneste', 'title_ru' => 'Услуга Issue 81',
    ]);
}

/** @param array<string, mixed> $override */
function issue81Request(ServicePage $service, array $override = []): ServiceRequest
{
    return ServiceRequest::query()->create(array_replace([
        'reference' => 'SR-81-'.Str::upper(Str::random(8)),
        'submission_token' => (string) Str::uuid(),
        'locale' => 'nb', 'source' => ServiceRequestSource::Public,
        'service_slug' => $service->slug, 'fulfilment' => 'workshop',
        'name' => 'Issue 81 customer', 'phone' => '90000000', 'phone_normalized' => '4790000000',
        'status' => ServiceRequestStatus::New, 'consent_at' => now(),
    ], $override));
}
