<?php

namespace App\Modules\NdiApi\Models;

use App\Modules\NdiApi\Enums\NdiAvailabilityStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'document_id', 'source_action', 'request_fingerprint', 'status',
    'error_code', 'response_summary_encrypted', 'requested_at', 'responded_at', 'expires_at',
])]
class NdiAvailabilityCheck extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'status' => NdiAvailabilityStatus::class,
            'response_summary_encrypted' => 'encrypted:array',
            'requested_at' => 'datetime',
            'responded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
