<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\TaxMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['supplier_id', 'code', 'currency', 'tax_mode', 'valid_from', 'valid_to'])]
class PriceList extends Model
{
    protected function casts(): array
    {
        return [
            'tax_mode' => TaxMode::class,
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(SkuPrice::class);
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(SkuPriceHistory::class);
    }
}
