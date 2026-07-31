<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['supplier_sku_id', 'price_list_id', 'amount', 'observed_at', 'source_artifact_id'])]
class SkuPrice extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'observed_at' => 'datetime',
        ];
    }

    public function supplierSku(): BelongsTo
    {
        return $this->belongsTo(SupplierSku::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }
}
