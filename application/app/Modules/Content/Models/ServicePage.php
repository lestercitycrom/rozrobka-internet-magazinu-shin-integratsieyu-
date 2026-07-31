<?php

namespace App\Modules\Content\Models;

use App\Modules\Content\Enums\ServicePagePublicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'slug', 'sort_order', 'publication_status',
    'title_nb', 'excerpt_nb', 'body_nb', 'seo_title_nb', 'seo_description_nb',
    'title_ru', 'excerpt_ru', 'body_ru', 'seo_title_ru', 'seo_description_ru',
])]
class ServicePage extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'publication_status' => ServicePagePublicationStatus::class,
        ];
    }
}
