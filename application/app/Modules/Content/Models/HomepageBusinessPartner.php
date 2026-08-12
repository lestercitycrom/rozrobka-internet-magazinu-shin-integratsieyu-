<?php

namespace App\Modules\Content\Models;

use App\Modules\Content\Enums\ContentPageStatus;
use App\Support\PublicHttpUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name',
    'media_asset_id',
    'website_url',
    'sort_order',
    'publication_status',
])]
final class HomepageBusinessPartner extends Model
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

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class)->where('is_active', true);
    }

    public function publicWebsiteUrl(): ?string
    {
        return PublicHttpUrl::sanitize($this->website_url);
    }
}
