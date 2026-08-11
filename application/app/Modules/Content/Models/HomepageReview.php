<?php

namespace App\Modules\Content\Models;

use App\Modules\Content\Enums\ContentPageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'sort_order',
    'publication_status',
    'quote_nb',
    'author_nb',
    'source_label_nb',
    'quote_ru',
    'author_ru',
    'source_label_ru',
    'source_url',
])]
final class HomepageReview extends Model
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
