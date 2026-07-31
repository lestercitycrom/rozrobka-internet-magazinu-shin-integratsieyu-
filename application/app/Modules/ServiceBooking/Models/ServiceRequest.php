<?php

namespace App\Modules\ServiceBooking\Models;

use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'reference', 'locale', 'service_slug', 'fulfilment', 'preferred_date',
    'preferred_window', 'vehicle', 'registration_number', 'tire_size', 'dekkhotell',
    'name', 'email', 'phone', 'address', 'note', 'status', 'admin_note', 'consent_at',
])]
class ServiceRequest extends Model
{
    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'dekkhotell' => 'boolean',
            'consent_at' => 'datetime',
            'status' => ServiceRequestStatus::class,
        ];
    }
}
