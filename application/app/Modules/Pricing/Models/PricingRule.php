<?php

namespace App\Modules\Pricing\Models;

use App\Modules\Catalog\Enums\TaxMode;
use App\Modules\Pricing\Enums\PricingRuleScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'scope',
    'scope_key',
    'source_price_list_code',
    'source_tax_mode',
    'currency',
    'markup_basis_points',
    'fixed_markup_minor',
    'rounding_increment_minor',
    'priority',
    'is_active',
    'starts_at',
    'ends_at',
])]
class PricingRule extends Model
{
    protected function casts(): array
    {
        return [
            'scope' => PricingRuleScope::class,
            'source_tax_mode' => TaxMode::class,
            'markup_basis_points' => 'integer',
            'fixed_markup_minor' => 'integer',
            'rounding_increment_minor' => 'integer',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
