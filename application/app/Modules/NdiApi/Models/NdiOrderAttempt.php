<?php

namespace App\Modules\NdiApi\Models;

use App\Modules\NdiApi\Enums\NdiSubmissionStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'customer_reference', 'request_fingerprint', 'status',
    'external_order_reference', 'error_code', 'response_summary_encrypted',
    'sales_status', 'document_status', 'carrier', 'tracking_summary_encrypted',
    'attempted_at', 'responded_at', 'status_checked_at', 'despatch_checked_at',
])]
class NdiOrderAttempt extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'status' => NdiSubmissionStatus::class,
            'response_summary_encrypted' => 'encrypted:array',
            'tracking_summary_encrypted' => 'encrypted:array',
            'attempted_at' => 'datetime',
            'responded_at' => 'datetime',
            'status_checked_at' => 'datetime',
            'despatch_checked_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
