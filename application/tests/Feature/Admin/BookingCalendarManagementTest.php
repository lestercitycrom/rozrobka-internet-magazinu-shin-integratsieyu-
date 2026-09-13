<?php

use App\Models\User;
use App\Modules\ServiceBooking\Models\BookingBlackout;
use App\Modules\ServiceBooking\Models\BookingScheduleInterval;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owners manage weekly intervals and blackouts in the custom admin', function (): void {
    $admin = User::factory()->owner()->create();

    $this->actingAs($admin)->get('/admin/booking-calendar')
        ->assertOk()->assertSee('Timeplan og kapasitet')->assertSee('data-testid="booking-calendar-unconfigured"', false);

    $this->actingAs($admin)->post('/admin/booking-calendar/intervals', [
        'weekday' => 1,
        'location' => 'workshop',
        'start_time' => '09:00',
        'end_time' => '12:00',
        'slot_minutes' => 30,
        'capacity' => 2,
        'is_active' => '1',
    ])->assertRedirect()->assertSessionHas('status');

    $interval = BookingScheduleInterval::query()->firstOrFail();
    expect($interval->capacity)->toBe(2)->and($interval->is_active)->toBeTrue();

    $this->actingAs($admin)->post('/admin/booking-calendar/intervals', [
        'weekday' => 1,
        'location' => 'both',
        'start_time' => '11:30',
        'end_time' => '13:00',
        'slot_minutes' => 30,
        'capacity' => 1,
        'is_active' => '1',
    ])->assertSessionHasErrors('start_time');

    $this->actingAs($admin)->post('/admin/booking-calendar/blackouts', [
        'location' => 'both',
        'starts_at' => '2026-10-01T09:00',
        'ends_at' => '2026-10-01T12:00',
        'reason' => 'Ferie',
        'is_active' => '1',
    ])->assertRedirect()->assertSessionHas('status');

    $blackout = BookingBlackout::query()->firstOrFail();
    expect($blackout->starts_at->utc()->format('Y-m-d H:i'))->toBe('2026-10-01 07:00')
        ->and($blackout->reason)->toBe('Ferie');

    $this->actingAs($admin)->put('/admin/booking-calendar/intervals/'.$interval->id, [
        'weekday' => 2,
        'location' => 'mobile',
        'start_time' => '10:00',
        'end_time' => '11:00',
        'slot_minutes' => 60,
        'capacity' => 1,
    ])->assertRedirect();
    expect($interval->fresh()->weekday)->toBe(2)->and($interval->fresh()->is_active)->toBeFalse();
});

test('calendar administration is localized for Russian QA and forbidden to customers', function (): void {
    $admin = User::factory()->owner()->create(['locale' => 'ru']);
    $customer = User::factory()->customer()->create();

    $this->actingAs($admin)->get('/admin/booking-calendar')
        ->assertOk()->assertSee('Расписание и вместимость')->assertSee('Рабочие часы не заданы');
    $this->actingAs($customer)->get('/admin/booking-calendar')->assertForbidden();
});
