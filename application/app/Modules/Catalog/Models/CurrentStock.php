<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['supplier_sku_id', 'warehouse_id', 'quantity', 'observed_at', 'source_artifact_id'])]
class CurrentStock extends Model
{
    protected $table = 'current_stock';

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'observed_at' => 'datetime',
        ];
    }

    public function supplierSku(): BelongsTo
    {
        return $this->belongsTo(SupplierSku::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
