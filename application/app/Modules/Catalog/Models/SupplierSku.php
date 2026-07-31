<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\CatalogState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'supplier_id',
    'product_id',
    'external_sku',
    'ean',
    'supplier_item_id',
    'source_description',
    'catalog_state',
])]
class SupplierSku extends Model
{
    protected function casts(): array
    {
        return [
            'catalog_state' => CatalogState::class,
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(SkuPrice::class);
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(SkuPriceHistory::class);
    }

    public function stockSnapshots(): HasMany
    {
        return $this->hasMany(StockSnapshot::class);
    }

    public function currentStock(): HasMany
    {
        return $this->hasMany(CurrentStock::class);
    }
}
