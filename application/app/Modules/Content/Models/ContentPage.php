<?php

namespace App\Modules\Content\Models;

use App\Modules\Content\Enums\ContentPageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['slug', 'sort_order', 'publication_status', 'title_nb', 'excerpt_nb', 'body_nb', 'meta_title_nb', 'meta_description_nb', 'title_ru', 'excerpt_ru', 'body_ru', 'meta_title_ru', 'meta_description_ru'])]
class ContentPage extends Model
{
    /** @var list<string> */
    public const ROUTABLE_SLUGS = ['om-oss', 'kontakt', 'personvern', 'kjopsvilkar'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'publication_status' => ContentPageStatus::class];
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
