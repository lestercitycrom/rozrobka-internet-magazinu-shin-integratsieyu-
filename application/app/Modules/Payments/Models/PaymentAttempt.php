<?php

namespace App\Modules\Payments\Models;

use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'provider', 'idempotency_key', 'provider_reference', 'status',
    'capture_idempotency_key', 'void_idempotency_key', 'amount_minor', 'currency',
    'metadata_encrypted', 'started_at', 'authorized_at', 'captured_at', 'voided_at',
    'completed_at',
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
            'metadata_encrypted' => 'encrypted:array',
            'started_at' => 'datetime',
            'authorized_at' => 'datetime',
            'captured_at' => 'datetime',
            'voided_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
