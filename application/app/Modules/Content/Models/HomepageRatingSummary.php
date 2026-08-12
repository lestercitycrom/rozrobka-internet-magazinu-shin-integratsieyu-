<?php

namespace App\Modules\Content\Models;

use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Enums\ReviewPlatform;
use App\Support\PublicHttpUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'platform',
    'rating',
    'review_count',
    'count_label_nb',
    'count_label_ru',
    'link_label_nb',
    'link_label_ru',
    'source_url',
    'sort_order',
    'publication_status',
])]
final class HomepageRatingSummary extends Model
{
    protected function casts(): array
    {
        return [
            'platform' => ReviewPlatform::class,
            'rating' => 'decimal:1',
            'review_count' => 'integer',
            'sort_order' => 'integer',
            'publication_status' => ContentPageStatus::class,
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('publication_status', ContentPageStatus::Published->value);
    }

    public function publicSourceUrl(): ?string
    {
        return PublicHttpUrl::sanitize($this->source_url);
    }
}
