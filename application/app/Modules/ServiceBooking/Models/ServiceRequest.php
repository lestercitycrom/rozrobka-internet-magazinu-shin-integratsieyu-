<?php

namespace App\Modules\ServiceBooking\Models;

use App\Models\User;
use App\Modules\ServiceBooking\Enums\ServiceRequestSource;
use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reference', 'submission_token', 'locale', 'source', 'created_by_user_id', 'client_id', 'service_slug', 'service_variant', 'fulfilment', 'assigned_van', 'preferred_date',
    'preferred_window', 'preferred_period_starts_at', 'preferred_period_ends_at', 'booking_schedule_interval_id', 'booking_slot_key', 'booking_starts_at', 'booking_ends_at',
    'vehicle', 'registration_number', 'tire_size', 'wheel_count', 'dekkhotell', 'dekkhotell_already_stored', 'dekkhotell_number',
    'additional_services',
    'vehicles', 'quote_min_minor', 'quote_max_minor', 'quote_currency',
    'name', 'email', 'phone', 'phone_normalized', 'address', 'district', 'note', 'status', 'admin_note', 'consent_at',
])]
class ServiceRequest extends Model
{
    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'booking_starts_at' => 'immutable_datetime',
            'booking_ends_at' => 'immutable_datetime',
            'preferred_period_starts_at' => 'immutable_datetime',
            'preferred_period_ends_at' => 'immutable_datetime',
            'dekkhotell' => 'boolean',
            'dekkhotell_already_stored' => 'boolean',
            'wheel_count' => 'integer',
            'additional_services' => 'array',
            'vehicles' => 'array',
            'quote_min_minor' => 'integer',
            'quote_max_minor' => 'integer',
            'consent_at' => 'datetime',
            'source' => ServiceRequestSource::class,
            'status' => ServiceRequestStatus::class,
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
