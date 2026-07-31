<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'width_mm',
    'width_inch',
    'aspect_ratio',
    'rim_diameter_in',
    'load_index',
    'speed_symbol',
    'construction',
    'season',
    'reinforced',
    'winter_type',
    'three_peak_mountain_snowflake',
    'ice_grip',
    'run_flat',
    'production_year',
])]
class TireSpec extends Model
{
    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected function casts(): array
    {
        return [
            'width_mm' => 'decimal:2',
            'width_inch' => 'decimal:2',
            'aspect_ratio' => 'decimal:2',
            'rim_diameter_in' => 'decimal:2',
            'reinforced' => 'boolean',
            'three_peak_mountain_snowflake' => 'boolean',
            'ice_grip' => 'boolean',
            'run_flat' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
