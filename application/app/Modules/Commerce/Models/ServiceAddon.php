<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Commerce\Enums\ServiceAddonApplicability;
use App\Modules\Commerce\Enums\ServiceAddonBillingMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'code', 'title_nb', 'title_ru', 'applicability', 'billing_mode', 'currency',
    'price_minor', 'is_active', 'sort_order',
])]
class ServiceAddon extends Model
{
    protected function casts(): array
    {
        return [
            'applicability' => ServiceAddonApplicability::class,
            'billing_mode' => ServiceAddonBillingMode::class,
            'price_minor' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @param Builder<ServiceAddon> $query */
    public function scopeAvailable(Builder $query): void
    {
        $query->where('is_active', true)
            ->where('currency', 'NOK')
            ->whereNotNull('price_minor')
            ->where('price_minor', '>', 0);
    }

    public function title(string $locale): string
    {
        return $locale === 'ru' ? $this->title_ru : $this->title_nb;
    }
}
