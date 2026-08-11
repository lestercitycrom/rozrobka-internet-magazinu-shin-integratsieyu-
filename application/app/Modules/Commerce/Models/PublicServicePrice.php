<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'code', 'category_code', 'category_title_nb', 'category_title_ru',
    'title_nb', 'title_ru', 'description_nb', 'description_ru',
    'price_minor', 'currency', 'is_active', 'sort_order',
])]
class PublicServicePrice extends Model
{
    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @param Builder<PublicServicePrice> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_active', true)
            ->where('currency', 'NOK')
            ->where('price_minor', '>', 0);
    }

    public function localized(string $field, string $locale): ?string
    {
        $value = $this->getAttribute($field.'_'.($locale === 'ru' ? 'ru' : 'nb'));

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
