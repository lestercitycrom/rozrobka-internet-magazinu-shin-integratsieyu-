<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'integration_profile', 'is_test_fixture', 'is_active'])]
class Supplier extends Model
{
    protected function casts(): array
    {
        return [
            'is_test_fixture' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function supplierSkus(): HasMany
    {
        return $this->hasMany(SupplierSku::class);
    }

    public function priceLists(): HasMany
    {
        return $this->hasMany(PriceList::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }
}
