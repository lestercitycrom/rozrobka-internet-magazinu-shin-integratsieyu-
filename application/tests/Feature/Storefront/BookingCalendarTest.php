<?php

use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Enums\ServiceRequestFlow;
use App\Modules\Content\Models\ServicePage;
use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use App\Modules\ServiceBooking\Models\BookingBlackout;
use App\Modules\ServiceBooking\Models\BookingScheduleInterval;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-12 06:00:00', 'Europe/Oslo'));
    ServicePage::query()->create([
        'slug' => 'calendar-seasonal',
        'sort_order' => 1,
        'publication_status' => ServicePagePublicationStatus::Published,
        'request_flow' => ServiceRequestFlow::Seasonal,
        'title_nb' => 'Sesongskift med kalender',
        'title_ru' => 'Сезонная замена по календарю',
    ]);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function calendarDate(string $modifier = 'next monday'): CarbonImmutable
{
    return CarbonImmutable::parse($modifier, 'Europe/Oslo')->startOfDay();
}

function calendarInterval(CarbonImmutable $date, array $overrides = []): BookingScheduleInterval
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

function calendarPayload(CarbonImmutable $date, array $overrides = []): array
{
    return [
        'submission_token' => (string) Str::uuid(),
        'fulfilment' => 'workshop',
        'preferred_date' => $date->format('Y-m-d'),
        'preferred_window' => 'Ettermiddag',
        'car_count' => 1,
        'vehicle' => 'Volvo XC60',
        'vehicle_type' => 'passenger',
        'rim_band' => 'r16-r18',
        'storage_plan' => 'none',
        'name' => 'Calendar Buyer',
        'email' => 'calendar@example.test',
        'phone' => '90000000',
        'consent' => '1',
        ...$overrides,
    ];
}

test('availability follows weekday location and blackout ranges', function (): void {
    $date = calendarDate();
    calendarInterval($date);
    BookingBlackout::query()->create([
        'location' => 'workshop',
        'starts_at' => $date->setTime(9, 30)->utc(),
        'ends_at' => $date->setTime(10, 30)->utc(),
        'reason' => 'Intern test',
        'is_active' => true,
    ]);

    $response = $this->getJson('/booking/availability?date='.$date->format('Y-m-d').'&fulfilment=workshop')
        ->assertOk()
        ->assertJsonPath('configured', true)
        ->assertJsonCount(2, 'slots');

    expect($response->json('slots.*.label'))->toBe(['09:00–09:30', '10:30–11:00']);

    $this->getJson('/booking/availability?date='.$date->addDay()->format('Y-m-d').'&fulfilment=workshop')
        ->assertOk()->assertJsonPath('configured', true)->assertJsonCount(0, 'slots');
    $this->getJson('/booking/availability?date='.$date->format('Y-m-d').'&fulfilment=mobile')
        ->assertOk()->assertJsonPath('configured', false)->assertJsonCount(0, 'slots');
});

test('a claimed slot cannot exceed capacity and cancelling releases it', function (): void {
    $date = calendarDate();
    calendarInterval($date);
    $slot = $this->getJson('/booking/availability?date='.$date->format('Y-m-d').'&fulfilment=workshop')->json('slots.0');

    $this->post('/tjenester/calendar-seasonal/foresporsel', calendarPayload($date, [
        'booking_slot_key' => $slot['key'],
        'preferred_window' => null,
    ]))->assertRedirect();

    $stored = ServiceRequest::query()->firstOrFail();
    expect($stored->booking_slot_key)->toBe($slot['key'])
        ->and($stored->booking_starts_at->utc()->format('Y-m-d H:i'))->toBe($date->setTime(9, 0)->utc()->format('Y-m-d H:i'))
        ->and($stored->preferred_window)->toBe('09:00–09:30');

    $this->post('/tjenester/calendar-seasonal/foresporsel', calendarPayload($date, [
        'booking_slot_key' => $slot['key'],
        'preferred_window' => null,
    ]))->assertSessionHasErrors('booking_slot_key');
    expect(ServiceRequest::query()->count())->toBe(1);

    $stored->update(['status' => ServiceRequestStatus::Cancelled]);
    $this->getJson('/booking/availability?date='.$date->format('Y-m-d').'&fulfilment=workshop')
        ->assertOk()->assertJsonPath('slots.0.key', $slot['key']);
});

test('forged or omitted slots are rejected whenever that location has a configured calendar', function (): void {
    $date = calendarDate();
    calendarInterval($date);

    $this->post('/tjenester/calendar-seasonal/foresporsel', calendarPayload($date, [
        'booking_slot_key' => null,
    ]))->assertSessionHasErrors('booking_slot_key');

    $this->post('/tjenester/calendar-seasonal/foresporsel', calendarPayload($date, [
        'booking_slot_key' => 'b1-999-'.$date->format('Ymd').'-0900',
        'preferred_window' => null,
    ]))->assertSessionHasErrors('booking_slot_key');

    expect(ServiceRequest::query()->count())->toBe(0);
});

test('without a configured calendar the request clearly falls back to a manually confirmed window', function (): void {
    $date = calendarDate();

    $this->get('/tjenester/calendar-seasonal/foresporsel')
        ->assertOk()
        ->assertSee('data-booking-manual', false)
        ->assertSee('Timeplanen er ikke satt opp')
        ->assertSee('name="preferred_window"', false);

    $this->post('/tjenester/calendar-seasonal/foresporsel', calendarPayload($date, [
        'preferred_window' => null,
    ]))->assertSessionHasErrors('preferred_window');

    $this->post('/tjenester/calendar-seasonal/foresporsel', calendarPayload($date))
        ->assertRedirect();
    expect(ServiceRequest::query()->firstOrFail()->booking_slot_key)->toBeNull();
});

test('the same submission token is idempotent and consumes capacity only once', function (): void {
    $date = calendarDate();
    calendarInterval($date, ['capacity' => 1]);
    $slotKey = $this->getJson('/booking/availability?date='.$date->format('Y-m-d').'&fulfilment=workshop')->json('slots.0.key');
    $token = (string) Str::uuid();
    $payload = calendarPayload($date, ['submission_token' => $token, 'booking_slot_key' => $slotKey, 'preferred_window' => null]);

    $first = $this->post('/tjenester/calendar-seasonal/foresporsel', $payload)->assertRedirect();
    $second = $this->post('/tjenester/calendar-seasonal/foresporsel', $payload)->assertRedirect();

    expect($second->headers->get('Location'))->toBe($first->headers->get('Location'))
        ->and(ServiceRequest::query()->count())->toBe(1)
        ->and(ServiceRequest::query()->firstOrFail()->submission_token)->toBe($token);
});

test('availability and manual fallback are translated in the Russian QA route', function (): void {
    config()->set('storefront.ru_preview_enabled', true);
    $date = calendarDate();
    calendarInterval($date);

    $this->get('/ru/tjenester/calendar-seasonal/foresporsel')
        ->assertOk()
        ->assertSee('Свободное время')
        ->assertSee('data-booking-availability', false);
    $this->getJson('/ru/booking/availability?date='.$date->format('Y-m-d').'&fulfilment=workshop')
        ->assertOk()->assertJsonPath('configured', true)->assertJsonCount(4, 'slots');
});
