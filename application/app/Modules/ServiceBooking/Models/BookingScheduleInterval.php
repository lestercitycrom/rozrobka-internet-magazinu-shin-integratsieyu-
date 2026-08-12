<?php

namespace App\Modules\ServiceBooking\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'weekday', 'location', 'start_time', 'end_time', 'slot_minutes', 'capacity', 'is_active',
])]
class BookingScheduleInterval extends Model
{
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'slot_minutes' => 'integer',
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
