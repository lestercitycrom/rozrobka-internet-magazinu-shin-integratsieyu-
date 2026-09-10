<?php

namespace App\Modules\Content\Models;

use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Enums\HomepageServiceCollection;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'content_key',
    'manifest_hash',
    'collection',
    'service_page_id',
    'sort_order',
    'publication_status',
    'title_nb',
    'description_nb',
    'cta_label_nb',
    'title_ru',
    'description_ru',
    'cta_label_ru',
    'cta_url',
    'media_asset_id',
])]
final class HomepageServiceCard extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'collection' => HomepageServiceCollection::class,
            'sort_order' => 'integer',
            'publication_status' => ContentPageStatus::class,
        ];
    }

    public function scopePublishedFor(Builder $query, HomepageServiceCollection $collection): Builder
    {
        return $query
            ->where('collection', $collection->value)
            ->where('publication_status', ContentPageStatus::Published->value)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('service_page_id')
                    ->orWhereHas('servicePage', fn (Builder $service): Builder => $service->where(
                        'publication_status',
                        ServicePagePublicationStatus::Published->value,
                    ));
            });
    }

    public function servicePage(): BelongsTo
    {
        return $this->belongsTo(ServicePage::class);
    }

    /** A deactivated asset must never remain publicly usable. */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class)->where('is_active', true);
    }
}
