<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'width_in',
    'diameter_in',
    'bolt_holes',
    'pcd_mm',
    'offset_et_mm',
    'center_bore_mm',
    'bolt_hole_boring',
    'color',
])]
class WheelSpec extends Model
{
    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected function casts(): array
    {
        return [
            'width_in' => 'decimal:2',
            'diameter_in' => 'decimal:2',
            'bolt_holes' => 'integer',
            'pcd_mm' => 'decimal:2',
            'offset_et_mm' => 'decimal:2',
            'center_bore_mm' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
