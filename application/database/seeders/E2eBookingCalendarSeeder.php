<?php

namespace Database\Seeders;

use App\Modules\ServiceBooking\Models\BookingScheduleInterval;
use Illuminate\Database\Seeder;
use LogicException;

final class E2eBookingCalendarSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['testing', 'local'])) {
            throw new LogicException('E2E booking hours are restricted to testing/local.');
        }

        foreach (range(1, 7) as $weekday) {
            BookingScheduleInterval::query()->updateOrCreate([
                'weekday' => $weekday,
                'location' => 'both',
                'start_time' => '09:00',
                'end_time' => '12:00',
            ], [
                'slot_minutes' => 30,
                'capacity' => 2,
                'is_active' => true,
            ]);
        }
    }
}
