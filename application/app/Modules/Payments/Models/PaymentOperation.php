<?php

namespace App\Modules\Payments\Models;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Enums\PaymentOperationStatus;
use App\Modules\Payments\Enums\PaymentOperationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'payment_attempt_id', 'actor_user_id', 'type', 'idempotency_key', 'status',
    'amount_minor', 'currency', 'items_encrypted', 'note', 'provider_reference', 'error_code',
    'started_at', 'completed_at',
])]
class PaymentOperation extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'type' => PaymentOperationType::class,
            'status' => PaymentOperationStatus::class,
            'amount_minor' => 'integer',
            'items_encrypted' => 'encrypted:array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
