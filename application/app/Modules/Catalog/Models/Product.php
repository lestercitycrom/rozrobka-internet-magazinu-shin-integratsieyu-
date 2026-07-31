<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Enums\PublicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'product_type',
    'brand_id',
    'model_name',
    'title',
    'publication_status',
    'first_seen_at',
    'last_catalog_seen_at',
])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'product_type' => ProductType::class,
            'publication_status' => PublicationStatus::class,
            'first_seen_at' => 'datetime',
            'last_catalog_seen_at' => 'datetime',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function supplierSkus(): HasMany
    {
        return $this->hasMany(SupplierSku::class);
    }

    public function tireSpec(): HasOne
    {
        return $this->hasOne(TireSpec::class);
    }

    public function tireLabel(): HasOne
    {
        return $this->hasOne(TireLabel::class);
    }

    public function wheelSpec(): HasOne
    {
        return $this->hasOne(WheelSpec::class);
    }
}
