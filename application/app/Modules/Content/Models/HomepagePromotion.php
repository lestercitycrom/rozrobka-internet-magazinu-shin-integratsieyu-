<?php

namespace App\Modules\Content\Models;

use App\Modules\Content\Enums\ContentPageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'sort_order',
    'publication_status',
    'title_nb',
    'body_nb',
    'cta_label_nb',
    'title_ru',
    'body_ru',
    'cta_label_ru',
    'cta_url',
])]
final class HomepagePromotion extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'publication_status' => ContentPageStatus::class,
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('publication_status', ContentPageStatus::Published->value);
    }
}
