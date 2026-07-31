<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['canonical_name', 'slug'])]
class Brand extends Model
{
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
