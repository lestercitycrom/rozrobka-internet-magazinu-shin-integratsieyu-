<?php

namespace App\Modules\Orders\Models;

use App\Modules\Catalog\Models\SupplierSku;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'supplier_sku_id', 'external_sku', 'product_title', 'quantity',
    'unit_price_minor', 'line_total_minor', 'currency', 'product_snapshot',
    'pricing_snapshot', 'source_price_observed_at',
])]
class OrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_minor' => 'integer',
            'line_total_minor' => 'integer',
            'product_snapshot' => 'array',
            'pricing_snapshot' => 'array',
            'source_price_observed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function supplierSku(): BelongsTo
    {
        return $this->belongsTo(SupplierSku::class);
    }
}
