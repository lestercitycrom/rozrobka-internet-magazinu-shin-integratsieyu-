<?php

namespace App\Modules\Payments\Models;

use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_id', 'provider', 'idempotency_key', 'provider_reference', 'provider_session_reference', 'status',
    'capture_idempotency_key', 'void_idempotency_key', 'refund_idempotency_key', 'amount_minor', 'currency',
    'payment_product_type', 'authorization_expires_at', 'captured_amount_minor', 'refunded_amount_minor', 'card_last_four',
    'metadata_encrypted', 'started_at', 'authorized_at', 'captured_at', 'voided_at', 'refunded_at', 'completed_at',
])]
class PaymentAttempt extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount_minor' => 'integer',
            'captured_amount_minor' => 'integer',
            'refunded_amount_minor' => 'integer',
            'metadata_encrypted' => 'encrypted:array',
            'started_at' => 'datetime',
            'authorized_at' => 'datetime',
            'authorization_expires_at' => 'datetime',
            'captured_at' => 'datetime',
            'voided_at' => 'datetime',
            'refunded_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(PaymentOperation::class);
    }

    public function remainingAuthorizedMinor(): int
    {
        return max(0, $this->amount_minor - $this->captured_amount_minor);
    }

    public function refundableMinor(): int
    {
        return max(0, $this->captured_amount_minor - $this->refunded_amount_minor);
    }

    public function authorizationExpired(): bool
    {
        return $this->authorization_expires_at !== null && $this->authorization_expires_at->isPast();
    }
}
