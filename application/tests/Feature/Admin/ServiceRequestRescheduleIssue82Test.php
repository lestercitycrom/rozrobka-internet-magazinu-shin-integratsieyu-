<?php

use App\Models\User;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Enums\ServiceRequestFlow;
use App\Modules\Content\Models\ServicePage;
use App\Modules\ServiceBooking\BookingCalendar;
use App\Modules\ServiceBooking\Enums\ServiceRequestSource;
use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use App\Modules\ServiceBooking\Models\BookingBlackout;
use App\Modules\ServiceBooking\Models\BookingScheduleInterval;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use App\Modules\ServiceBooking\Models\ServiceRequestEvent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-20 08:00:00', 'Europe/Oslo'));
});

afterEach(fn () => CarbonImmutable::setTestNow());

function issue82Service(): ServicePage
{
    return ServicePage::query()->create([
        'slug' => 'issue-82-seasonal',
        'sort_order' => 1,
        'publication_status' => ServicePagePublicationStatus::Published,
        'request_flow' => ServiceRequestFlow::Seasonal,
        'title_nb' => 'Sesongskift',
        'title_ru' => 'Сезонная замена',
    ]);
}

function issue82Interval(CarbonImmutable $date, array $overrides = []): BookingScheduleInterval
{
    return BookingScheduleInterval::query()->create([
        'weekday' => $date->isoWeekday(),
        'location' => 'workshop',
        'start_time' => '09:00',
        'end_time' => '11:00',
        'slot_minutes' => 30,
        'capacity' => 1,
        'is_active' => true,
        ...$overrides,
    ]);
}

function issue82Vehicle(string $registration = 'MOVE8201'): array
{
    return [
        'vehicle' => 'Volvo XC60',
        'registration_number' => $registration,
        'tire_size' => '235/55 R18',
        'vehicle_type' => 'passenger',
        'rim_band' => 'r16-r18',
        'storage_plan' => 'renew',
        'storage_number' => '00082-A',
    ];
}

function issue82Request(ServicePage $service, array $overrides = []): ServiceRequest
{
    return ServiceRequest::query()->create([
        'reference' => 'SR-82-'.Str::upper(Str::random(6)),
        'locale' => 'nb',
        'source' => ServiceRequestSource::AdminManual,
        'service_slug' => $service->slug,
        'fulfilment' => 'workshop',
        'preferred_date' => '2026-10-22',
        'preferred_window' => 'Etter kl. 12',
        'vehicles' => [issue82Vehicle()],
        'vehicle' => 'Volvo XC60',
        'registration_number' => 'MOVE8201',
        'tire_size' => '235/55 R18',
        'dekkhotell' => true,
        'quote_min_minor' => 123400,
        'quote_max_minor' => 145600,
        'quote_currency' => 'NOK',
        'name' => 'Issue 82 Customer',
        'email' => 'issue82@example.test',
        'phone' => '90000082',
        'phone_normalized' => '4790000082',
        'address' => 'Ringvegen 200',
        'district' => 'Tromsø',
        'note' => 'Keep every snapshot',
        'admin_note' => 'Keep internal note',
        'status' => ServiceRequestStatus::New,
        ...$overrides,
    ]);
}

function issue82ReschedulePayload(ServiceRequest $request, string $date, string $slotKey, array $overrides = []): array
{
    return [
        'booking_date' => $date,
        'booking_slot_key' => $slotKey,
        'expected_version' => $request->fresh()->operation_version,
        'command_id' => (string) Str::uuid(),
        'confirm_reschedule' => '1',
        ...$overrides,
    ];
}

test('operations assigns an overdue unconfirmed request to one real slot without changing business snapshots', function (): void {
    $service = issue82Service();
    $operations = User::factory()->operations()->create(['locale' => 'nb']);
    $date = CarbonImmutable::parse('2026-10-22', 'Europe/Oslo');
    issue82Interval($date);
    $request = issue82Request($service, ['preferred_date' => '2026-10-01']);
    $original = $request->only([
        'id', 'reference', 'client_id', 'service_slug', 'fulfilment', 'vehicles', 'quote_min_minor',
        'quote_max_minor', 'quote_currency', 'name', 'email', 'phone', 'address', 'note', 'admin_note',
    ]);
    $createdAt = $request->created_at->toISOString();
    $slot = app(BookingCalendar::class)->availabilityForRequest($request, $date)['slots']->firstOrFail();
    $returnTo = route('admin.service-requests.day', ['date' => '2026-10-22', 'resource' => 'workshop'], false);

    $this->actingAs($operations)->get(route('admin.service-requests.reschedule.edit', [
        'serviceRequest' => $request, 'date' => '2026-10-22', 'return_to' => $returnTo,
    ]))->assertOk()->assertSee('data-testid="reschedule-form"', false)->assertSee('09:00–09:30');

    $response = $this->actingAs($operations)->post(
        route('admin.service-requests.reschedule.update', $request),
        issue82ReschedulePayload($request, '2026-10-22', $slot->key, ['return_to' => $returnTo]),
    );
    $response->assertSessionHasNoErrors()->assertRedirect($returnTo);

    $request->refresh();
    expect($request->only(array_keys($original)))->toBe($original)
        ->and($request->created_at->toISOString())->toBe($createdAt)
        ->and($request->booking_slot_key)->toBe($slot->key)
        ->and($request->booking_starts_at->utc()->equalTo($slot->startsAt))->toBeTrue()
        ->and($request->preferred_period_starts_at)->toBeNull()
        ->and($request->operation_version)->toBe(2)
        ->and(ServiceRequestEvent::query()->where('service_request_id', $request->id)->where('command', 'reschedule')->count())->toBe(1);

    $event = ServiceRequestEvent::query()->sole();
    expect($event->actor_user_id)->toBe($operations->id)
        ->and($event->changes)->toHaveKeys([
            'preferred_date', 'preferred_window', 'booking_schedule_interval_id', 'booking_slot_key',
            'booking_starts_at', 'booking_ends_at',
        ])
        ->and($event->changes)->not->toHaveKey('name')
        ->and($event->changes)->not->toHaveKey('phone');

    $this->actingAs($operations)->get(route('admin.service-requests.show', $request))
        ->assertOk()->assertSee(__('service_requests.command_reschedule'))->assertSee('09:00–09:30');
});

test('moving an exact booking frees only the old slot and occupies only the new slot', function (): void {
    $service = issue82Service();
    $operations = User::factory()->operations()->create();
    $oldDate = CarbonImmutable::parse('2026-10-22', 'Europe/Oslo');
    $newDate = CarbonImmutable::parse('2026-10-23', 'Europe/Oslo');
    $oldInterval = issue82Interval($oldDate);
    issue82Interval($newDate);
    $request = issue82Request($service, [
        'preferred_date' => $oldDate->format('Y-m-d'),
        'preferred_window' => '09:00–09:30',
        'booking_schedule_interval_id' => $oldInterval->id,
        'booking_slot_key' => "b1-{$oldInterval->id}-20261022-0900",
        'booking_starts_at' => $oldDate->setTime(9, 0)->utc(),
        'booking_ends_at' => $oldDate->setTime(9, 30)->utc(),
    ]);
    $newSlot = app(BookingCalendar::class)->availabilityForRequest($request, $newDate)['slots']->firstOrFail();

    $this->actingAs($operations)->post(
        route('admin.service-requests.reschedule.update', $request),
        issue82ReschedulePayload($request, $newDate->format('Y-m-d'), $newSlot->key),
    )->assertSessionHasNoErrors();

    expect(ServiceRequest::query()->count())->toBe(1)
        ->and($request->fresh()->booking_slot_key)->toBe($newSlot->key)
        ->and(app(BookingCalendar::class)->availability('workshop', $oldDate)['slots']->pluck('key'))
        ->toContain("b1-{$oldInterval->id}-20261022-0900")
        ->and(app(BookingCalendar::class)->availability('workshop', $newDate)['slots']->pluck('key'))
        ->not->toContain($newSlot->key);
});

test('full blocked blacked out forged and stale slots leave the original booking intact', function (): void {
    $service = issue82Service();
    $operations = User::factory()->operations()->create();
    $oldDate = CarbonImmutable::parse('2026-10-22', 'Europe/Oslo');
    $targetDate = CarbonImmutable::parse('2026-10-23', 'Europe/Oslo');
    $oldInterval = issue82Interval($oldDate);
    $targetInterval = issue82Interval($targetDate);
    $oldKey = "b1-{$oldInterval->id}-20261022-0900";
    $targetKey = "b1-{$targetInterval->id}-20261023-0900";
    $request = issue82Request($service, [
        'booking_schedule_interval_id' => $oldInterval->id,
        'booking_slot_key' => $oldKey,
        'booking_starts_at' => $oldDate->setTime(9, 0)->utc(),
        'booking_ends_at' => $oldDate->setTime(9, 30)->utc(),
    ]);
    issue82Request($service, [
        'reference' => 'SR-82-OCCUPIED', 'registration_number' => 'OTHER82',
        'booking_schedule_interval_id' => $targetInterval->id,
        'booking_slot_key' => $targetKey,
        'booking_starts_at' => $targetDate->setTime(9, 0)->utc(),
        'booking_ends_at' => $targetDate->setTime(9, 30)->utc(),
    ]);

    foreach ([$targetKey, 'b1-999-20261023-0900'] as $candidate) {
        $this->actingAs($operations)->post(
            route('admin.service-requests.reschedule.update', $request),
            issue82ReschedulePayload($request, '2026-10-23', $candidate),
        )->assertSessionHasErrors('booking_slot_key');
        $current = $request->fresh();
        expect($current->booking_slot_key)->toBe($oldKey)
            ->and($current->operation_version)->toBe(1);
    }

    BookingBlackout::query()->create([
        'location' => 'workshop',
        'starts_at' => $targetDate->setTime(9, 30)->utc(),
        'ends_at' => $targetDate->setTime(10, 0)->utc(),
        'is_active' => true,
    ]);
    $blockedKey = "b1-{$targetInterval->id}-20261023-0930";
    $this->actingAs($operations)->post(
        route('admin.service-requests.reschedule.update', $request),
        issue82ReschedulePayload($request, '2026-10-23', $blockedKey),
    )->assertSessionHasErrors('booking_slot_key');

    $stale = issue82ReschedulePayload($request, '2026-10-23', "b1-{$targetInterval->id}-20261023-1000", ['expected_version' => 1]);
    $request->forceFill(['operation_version' => 2, 'admin_note' => 'Concurrent change'])->save();
    $this->actingAs($operations)->post(route('admin.service-requests.reschedule.update', $request), $stale)
        ->assertRedirect(route('admin.service-requests.reschedule.edit', [
            'serviceRequest' => $request,
            'date' => '2026-10-23',
        ]))
        ->assertSessionHasErrors('expected_version');
    $current = $request->fresh();
    expect($current->booking_slot_key)->toBe($oldKey)
        ->and($current->admin_note)->toBe('Concurrent change')
        ->and(ServiceRequestEvent::query()->count())->toBe(0);
});

test('same slot and replayed command are idempotent even after a later move', function (): void {
    $service = issue82Service();
    $operations = User::factory()->operations()->create();
    $date = CarbonImmutable::parse('2026-10-22', 'Europe/Oslo');
    $interval = issue82Interval($date);
    $key = "b1-{$interval->id}-20261022-0900";
    $request = issue82Request($service, [
        'booking_schedule_interval_id' => $interval->id,
        'booking_slot_key' => $key,
        'booking_starts_at' => $date->setTime(9, 0)->utc(),
        'booking_ends_at' => $date->setTime(9, 30)->utc(),
    ]);
    $sameCommand = (string) Str::uuid();
    $samePayload = issue82ReschedulePayload($request, '2026-10-22', $key, ['command_id' => $sameCommand]);

    $this->actingAs($operations)->post(
        route('admin.service-requests.reschedule.update', $request),
        issue82ReschedulePayload($request, '2026-10-23', $key),
    )->assertSessionHasErrors('booking_slot_key');
    expect(ServiceRequestEvent::query()->count())->toBe(0);

    $this->actingAs($operations)->post(route('admin.service-requests.reschedule.update', $request), $samePayload)
        ->assertSessionHasNoErrors();
    $this->actingAs($operations)->post(route('admin.service-requests.reschedule.update', $request), $samePayload)
        ->assertSessionHasNoErrors();
    expect($request->fresh()->operation_version)->toBe(1)
        ->and(ServiceRequestEvent::query()->where('command_id', $sameCommand)->count())->toBe(1);

    $newKey = "b1-{$interval->id}-20261022-0930";
    $this->actingAs($operations)->post(
        route('admin.service-requests.reschedule.update', $request),
        issue82ReschedulePayload($request, '2026-10-22', $newKey),
    )->assertSessionHasNoErrors();
    $current = $request->fresh();
    expect($current->booking_slot_key)->toBe($newKey)->and($current->operation_version)->toBe(2);

    $this->actingAs($operations)->post(route('admin.service-requests.reschedule.update', $request), $samePayload)
        ->assertSessionHasNoErrors();
    $current = $request->fresh();
    expect($current->booking_slot_key)->toBe($newKey)
        ->and($current->operation_version)->toBe(2)
        ->and(ServiceRequestEvent::query()->where('command_id', $sameCommand)->count())->toBe(1);
});

test('reschedule is role protected', function (): void {
    $service = issue82Service();
    $owner = User::factory()->owner()->create();
    $operations = User::factory()->operations()->create();
    $editor = User::factory()->editor()->create();
    $customer = User::factory()->customer()->create();
    $inactive = User::factory()->operations()->create(['is_active' => false]);
    $date = CarbonImmutable::parse('2026-10-22', 'Europe/Oslo');
    issue82Interval($date);
    $request = issue82Request($service);
    $slot = app(BookingCalendar::class)->availabilityForRequest($request, $date)['slots']->firstOrFail();

    foreach ([$editor, $customer] as $forbidden) {
        $this->actingAs($forbidden)->get(route('admin.service-requests.reschedule.edit', $request))->assertForbidden();
        $this->actingAs($forbidden)->post(
            route('admin.service-requests.reschedule.update', $request),
            issue82ReschedulePayload($request, '2026-10-22', $slot->key),
        )->assertForbidden();
    }
    $this->actingAs($inactive)->get(route('admin.service-requests.reschedule.edit', $request))->assertForbidden();
    Auth::logout();
    $this->get(route('admin.service-requests.reschedule.edit', $request))->assertRedirect(route('admin.login'));
    $this->actingAs($owner)->get(route('admin.service-requests.reschedule.edit', $request))->assertOk();
    $this->actingAs($operations)->get(route('admin.service-requests.reschedule.edit', $request))->assertOk();

});

test('an ineligible reschedule page terminates on the request card with a clear error', function (string $scenario, string $message): void {
    $service = issue82Service();
    $operations = User::factory()->operations()->create(['locale' => 'nb']);
    $overrides = match ($scenario) {
        'closed' => ['status' => ServiceRequestStatus::Closed],
        'cancelled' => ['status' => ServiceRequestStatus::Cancelled],
        'ended' => [
            'booking_slot_key' => 'b1-1-20261019-0900',
            'booking_starts_at' => '2026-10-19 07:00:00',
            'booking_ends_at' => '2026-10-19 07:30:00',
        ],
        'three-vehicles' => [
            'vehicles' => [issue82Vehicle('ONE82'), issue82Vehicle('TWO82'), issue82Vehicle('THREE82')],
        ],
    };
    $request = issue82Request($service, $overrides);
    $returnTo = route('admin.service-requests.index', ['q' => $request->reference, 'selected' => $request->id], false);
    $editUrl = route('admin.service-requests.reschedule.edit', [
        'serviceRequest' => $request,
        'return_to' => $returnTo,
    ]);
    $showUrl = route('admin.service-requests.show', [
        'request' => $request,
        'return_to' => $returnTo,
    ]);

    $this->actingAs($operations)->get($editUrl)
        ->assertRedirect($showUrl)
        ->assertSessionHasErrors('booking_slot_key');

    $this->followingRedirects()->get($editUrl)
        ->assertOk()
        ->assertViewIs('admin.requests.show')
        ->assertSee('data-testid="request-page-errors"', false)
        ->assertSee(__($message));
})->with([
    'closed request' => ['closed', 'service_requests.validation.reschedule_status'],
    'cancelled request' => ['cancelled', 'service_requests.validation.reschedule_status'],
    'ended booking' => ['ended', 'service_requests.validation.reschedule_past'],
    'three vehicles' => ['three-vehicles', 'service_requests.validation.reschedule_vehicle_limit'],
]);

test('a stale reschedule after another operator cancels ends safely without changing the booking', function (): void {
    $service = issue82Service();
    $operatorA = User::factory()->operations()->create(['locale' => 'nb']);
    $operatorB = User::factory()->operations()->create(['locale' => 'nb']);
    $oldDate = CarbonImmutable::parse('2026-10-22', 'Europe/Oslo');
    $newDate = CarbonImmutable::parse('2026-10-23', 'Europe/Oslo');
    $oldInterval = issue82Interval($oldDate);
    $newInterval = issue82Interval($newDate);
    $oldKey = "b1-{$oldInterval->id}-20261022-0900";
    $request = issue82Request($service, [
        'booking_schedule_interval_id' => $oldInterval->id,
        'booking_slot_key' => $oldKey,
        'booking_starts_at' => $oldDate->setTime(9, 0)->utc(),
        'booking_ends_at' => $oldDate->setTime(9, 30)->utc(),
    ]);
    $returnTo = route('admin.service-requests.index', ['q' => $request->reference, 'selected' => $request->id], false);
    $stalePayload = issue82ReschedulePayload(
        $request,
        '2026-10-23',
        "b1-{$newInterval->id}-20261023-0900",
        ['return_to' => $returnTo],
    );

    $this->actingAs($operatorA)->get(route('admin.service-requests.reschedule.edit', [
        'serviceRequest' => $request,
        'date' => '2026-10-23',
        'return_to' => $returnTo,
    ]))->assertOk();

    $this->actingAs($operatorB)->patch(route('admin.service-requests.quick-action', $request), [
        'command' => 'status',
        'command_id' => (string) Str::uuid(),
        'expected_version' => 1,
        'status' => 'cancelled',
    ])->assertSessionHasNoErrors();

    $showUrl = route('admin.service-requests.show', [
        'request' => $request,
        'return_to' => $returnTo,
    ]);
    $response = $this->actingAs($operatorA)->post(
        route('admin.service-requests.reschedule.update', $request),
        $stalePayload,
    );
    $response->assertRedirect($showUrl)->assertSessionHasErrors('booking_slot_key');
    $this->followingRedirects()->post(
        route('admin.service-requests.reschedule.update', $request),
        $stalePayload,
    )
        ->assertOk()
        ->assertViewIs('admin.requests.show')
        ->assertSee('data-testid="request-page-errors"', false)
        ->assertSee(__('service_requests.validation.reschedule_status'));

    $request->refresh();
    expect($request->status)->toBe(ServiceRequestStatus::Cancelled)
        ->and($request->booking_slot_key)->toBe($oldKey)
        ->and($request->booking_starts_at->utc()->equalTo($oldDate->setTime(9, 0)->utc()))->toBeTrue()
        ->and($request->operation_version)->toBe(2)
        ->and(ServiceRequestEvent::query()->where('service_request_id', $request->id)->count())->toBe(1)
        ->and(ServiceRequestEvent::query()->where('service_request_id', $request->id)->where('command', 'reschedule')->count())->toBe(0);
});

test('invalid reschedule dates and flashed hidden values reach one usable final page', function (): void {
    $service = issue82Service();
    $operations = User::factory()->operations()->create(['locale' => 'nb']);
    $date = CarbonImmutable::parse('2026-10-22', 'Europe/Oslo');
    issue82Interval($date);
    $request = issue82Request($service);
    $canonicalUrl = route('admin.service-requests.reschedule.edit', $request);

    foreach (['not-a-date', '2026-10-19'] as $invalidDate) {
        $invalidUrl = route('admin.service-requests.reschedule.edit', [
            'serviceRequest' => $request,
            'date' => $invalidDate,
        ]);
        $this->actingAs($operations)->get($invalidUrl)
            ->assertRedirect($canonicalUrl)
            ->assertSessionHasErrors('date');
        $this->followingRedirects()->get($invalidUrl)
            ->assertOk()
            ->assertViewIs('admin.requests.reschedule')
            ->assertSee('data-testid="reschedule-errors"', false);
    }

    $slot = app(BookingCalendar::class)->availabilityForRequest($request, $date)['slots']->firstOrFail();
    $payload = issue82ReschedulePayload($request, '2026-10-22', $slot->key, [
        'expected_version' => 0,
        'command_id' => 'not-a-uuid',
    ]);
    $payload['booking_date'] = ['not-a-date'];

    $this->from($canonicalUrl)->actingAs($operations)->post(
        route('admin.service-requests.reschedule.update', $request),
        $payload,
    )->assertRedirect($canonicalUrl)->assertSessionHasErrors(['booking_date', 'expected_version', 'command_id']);

    $this->from($canonicalUrl)->followingRedirects()->post(
        route('admin.service-requests.reschedule.update', $request),
        $payload,
    )
        ->assertOk()
        ->assertViewIs('admin.requests.reschedule')
        ->assertViewHas('commandId', fn (string $commandId): bool => Str::isUuid($commandId))
        ->assertViewHas('date', fn (CarbonImmutable $selected): bool => $selected->format('Y-m-d') === '2026-10-22')
        ->assertSee('name="booking_date" value="2026-10-22"', false)
        ->assertSee('name="expected_version" value="1"', false)
        ->assertSee('data-testid="reschedule-errors"', false);
});

test('ambiguous and nonexistent Oslo wall times are never offered as exact slots', function (): void {
    issue82Service();
    $calendar = app(BookingCalendar::class);
    $fallBack = CarbonImmutable::parse('2026-10-25', 'Europe/Oslo');
    issue82Interval($fallBack, ['start_time' => '02:00', 'end_time' => '03:00', 'slot_minutes' => 30]);
    expect($calendar->availability('workshop', $fallBack)['slots'])->toBeEmpty();

    BookingScheduleInterval::query()->delete();
    $springForward = CarbonImmutable::parse('2027-03-28', 'Europe/Oslo');
    issue82Interval($springForward, ['start_time' => '02:00', 'end_time' => '03:00', 'slot_minutes' => 30]);
    expect($calendar->availability('workshop', $springForward)['slots'])->toBeEmpty();
});

test('Russian reschedule UI renders the same safe workflow at desktop and mobile markup boundaries', function (): void {
    $service = issue82Service();
    $operations = User::factory()->operations()->russian()->create();
    $date = CarbonImmutable::parse('2026-10-22', 'Europe/Oslo');
    issue82Interval($date);
    $request = issue82Request($service, ['locale' => 'ru']);

    $this->actingAs($operations)->get(route('admin.service-requests.reschedule.edit', [
        'serviceRequest' => $request, 'date' => '2026-10-22',
    ]))->assertOk()
        ->assertSee('Назначить точное время')
        ->assertSee('Новое время')
        ->assertSee('data-testid="reschedule-date-form"', false)
        ->assertSee('data-testid="reschedule-form"', false)
        ->assertDontSee('service_requests.reschedule');
});
