<?php

namespace App\Modules\Orders\Models;

use App\Modules\Commerce\Enums\ServiceAddonApplicability;
use App\Modules\Commerce\Enums\ServiceAddonBillingMode;
use App\Modules\Commerce\Models\ServiceAddon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'service_addon_id', 'service_code', 'title_nb', 'title_ru',
    'applicability', 'billing_mode', 'quantity', 'unit_price_minor',
    'line_total_minor', 'currency', 'service_snapshot',
])]
class OrderServiceItem extends Model
{
    protected function casts(): array
    {
        return [
            'applicability' => ServiceAddonApplicability::class,
            'billing_mode' => ServiceAddonBillingMode::class,
            'quantity' => 'integer',
            'unit_price_minor' => 'integer',
            'line_total_minor' => 'integer',
            'service_snapshot' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function serviceAddon(): BelongsTo
    {
        return $this->belongsTo(ServiceAddon::class);
    }
}
