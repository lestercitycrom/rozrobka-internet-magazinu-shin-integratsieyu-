<?php

namespace App\Modules\ServiceBooking\Models;

use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'reference', 'locale', 'service_slug', 'service_variant', 'fulfilment', 'preferred_date',
    'preferred_window', 'vehicle', 'registration_number', 'tire_size', 'wheel_count', 'dekkhotell',
    'additional_services',
    'name', 'email', 'phone', 'address', 'note', 'status', 'admin_note', 'consent_at',
])]
class ServiceRequest extends Model
{
    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'dekkhotell' => 'boolean',
            'wheel_count' => 'integer',
            'additional_services' => 'array',
            'consent_at' => 'datetime',
            'status' => ServiceRequestStatus::class,
        ];
    }
}
