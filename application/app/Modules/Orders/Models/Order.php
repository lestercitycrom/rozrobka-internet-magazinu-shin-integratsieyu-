<?php

namespace App\Modules\Orders\Models;

use App\Modules\NdiApi\Models\NdiAvailabilityCheck;
use App\Modules\NdiApi\Models\NdiOrderAttempt;
use App\Modules\Orders\Enums\FulfilmentMethod;
use App\Modules\Orders\Enums\NdiOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Payments\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'public_reference', 'idempotency_key', 'locale', 'status', 'payment_status', 'ndi_status',
    'currency', 'subtotal_minor', 'total_minor', 'fulfilment_method', 'customer_name',
    'customer_email', 'customer_phone', 'vehicle_registration', 'customer_note',
    'terms_accepted_at', 'privacy_accepted_at', 'pricing_snapshot_at', 'submitted_at',
])]
class Order extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'ndi_status' => NdiOrderStatus::class,
            'fulfilment_method' => FulfilmentMethod::class,
            'subtotal_minor' => 'integer',
            'total_minor' => 'integer',
            'terms_accepted_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
            'pricing_snapshot_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function serviceItems(): HasMany
    {
        return $this->hasMany(OrderServiceItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function ndiAvailabilityChecks(): HasMany
    {
        return $this->hasMany(NdiAvailabilityCheck::class);
    }

    public function ndiOrderAttempt(): HasOne
    {
        return $this->hasOne(NdiOrderAttempt::class);
    }

    public function paymentAttempt(): HasOne
    {
        return $this->hasOne(PaymentAttempt::class);
    }
}
