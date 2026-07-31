<?php

namespace Database\Seeders;

use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Models\ContentPage;
use Illuminate\Database\Seeder;

/** Review placeholders, not legal or contact content. Existing editorial work is preserved. */
class ContentPageSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['om-oss', 'kontakt', 'personvern', 'kjopsvilkar'] as $order => $slug) {
            ContentPage::query()->firstOrCreate(['slug' => $slug], [
                'sort_order' => ($order + 1) * 10, 'publication_status' => ContentPageStatus::Draft,
                'title_nb' => 'Trenger redaksjonell gjennomgang', 'excerpt_nb' => 'Denne siden er ikke publisert før innholdet er kontrollert.', 'body_nb' => 'Trenger gjennomgang før publisering.', 'meta_title_nb' => null, 'meta_description_nb' => null,
                'title_ru' => 'Требуется редакционная проверка', 'excerpt_ru' => 'Страница не публикуется до проверки содержания.', 'body_ru' => 'Требуется проверка перед публикацией.', 'meta_title_ru' => null, 'meta_description_ru' => null,
            ]);
        }
    }
}
