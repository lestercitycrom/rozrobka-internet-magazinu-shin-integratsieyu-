<?php

namespace App\Modules\ServiceBooking\Models;

use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'reference', 'submission_token', 'locale', 'service_slug', 'service_variant', 'fulfilment', 'preferred_date',
    'preferred_window', 'booking_schedule_interval_id', 'booking_slot_key', 'booking_starts_at', 'booking_ends_at',
    'vehicle', 'registration_number', 'tire_size', 'wheel_count', 'dekkhotell',
    'additional_services',
    'vehicles', 'quote_min_minor', 'quote_max_minor', 'quote_currency',
    'name', 'email', 'phone', 'address', 'note', 'status', 'admin_note', 'consent_at',
])]
class ServiceRequest extends Model
{
    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'booking_starts_at' => 'immutable_datetime',
            'booking_ends_at' => 'immutable_datetime',
            'dekkhotell' => 'boolean',
            'wheel_count' => 'integer',
            'additional_services' => 'array',
            'vehicles' => 'array',
            'quote_min_minor' => 'integer',
            'quote_max_minor' => 'integer',
            'consent_at' => 'datetime',
            'status' => ServiceRequestStatus::class,
        ];
    }
}
