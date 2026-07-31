<?php

namespace App\Modules\Content\Models;

use App\Modules\Content\Enums\ServicePagePublicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['slug', 'sort_order', 'publication_status', 'title_nb', 'excerpt_nb', 'body_nb', 'seo_title_nb', 'seo_description_nb', 'title_ru', 'excerpt_ru', 'body_ru', 'seo_title_ru', 'seo_description_ru'])]
class ServicePage extends Model
{
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'publication_status' => ServicePagePublicationStatus::class];
    }

    public function mediaAssignments(): MorphMany
    {
        return $this->morphMany(MediaAssignment::class, 'assignable');
    }

    public function hero(): ?MediaAsset
    {
        return $this->mediaAssignments()->where('role', 'hero')->with('asset')->first()?->asset;
    }
}
