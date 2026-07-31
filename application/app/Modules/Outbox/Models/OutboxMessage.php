<?php

namespace App\Modules\Outbox\Models;

use App\Modules\Outbox\Enums\OutboxStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'type', 'aggregate_type', 'aggregate_id', 'idempotency_key', 'payload_encrypted',
    'status', 'attempts', 'available_at', 'locked_at', 'delivered_at', 'last_error_code',
])]
class OutboxMessage extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'payload_encrypted' => 'encrypted:array',
            'status' => OutboxStatus::class,
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'locked_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }
}
