<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'rolling_resistance',
    'wet_grip',
    'noise_db',
    'noise_class',
    'eu_tyre_class',
    'eu_label_regulation',
    'eprel_url',
])]
class TireLabel extends Model
{
    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected function casts(): array
    {
        return [
            'noise_db' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
