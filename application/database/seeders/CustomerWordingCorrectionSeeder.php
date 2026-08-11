<?php

namespace Database\Seeders;

use App\Modules\Content\Models\ContentPage;
use App\Modules\Content\Models\ServicePage;
use Illuminate\Database\Seeder;

/** Applies only customer-confirmed wording corrections without replacing editor-owned content. */
final class CustomerWordingCorrectionSeeder extends Seeder
{
    /** @var list<string> */
    private const SERVICE_FIELDS = [
        'title_nb',
        'excerpt_nb',
        'body_nb',
        'seo_title_nb',
        'seo_description_nb',
    ];

    /** @var list<string> */
    private const CONTENT_FIELDS = [
        'title_nb',
        'excerpt_nb',
        'body_nb',
        'meta_title_nb',
        'meta_description_nb',
    ];

    public function run(): void
    {
        ServicePage::query()->each(function (ServicePage $page): void {
            $replacements = [
                'Én' => 'En',
                'én' => 'en',
            ];

            if ($page->slug === 'mobilt-dekkskift') {
                $replacements += [
                    'Mobilt dekkskift' => 'Dekkskift hjemme hos deg',
                    'mobilt dekkskift' => 'dekkskift hjemme hos deg',
                    'Mobil dekkservice' => 'Dekkskift hjemme hos deg',
                    'mobil dekkservice' => 'dekkskift hjemme hos deg',
                ];
            }

            $this->replaceInFields($page, self::SERVICE_FIELDS, $replacements);
        });

        ContentPage::query()->each(function (ContentPage $page): void {
            $replacements = [
                'Én' => 'En',
                'én' => 'en',
                'en ordresammendrag' => 'et ordresammendrag',
            ];

            if ($page->slug === 'om-oss') {
                $replacements += [
                    'både mobil service og eget verksted' => 'dekkskift hjemme hos deg og eget verksted',
                    'ved verkstedet eller som mobil tjeneste når forholdene og oppdraget gjør det mulig' => 'hjemme hos deg, på jobb eller på verkstedet når forholdene gjør det mulig',
                    'med mobil service, verksted og bedriftsløsninger' => 'med dekkskift hjemme hos deg, verksted og bedriftsløsninger',
                ];
            }

            $this->replaceInFields($page, self::CONTENT_FIELDS, $replacements);
        });
    }

    /**
     * @param  list<string>  $fields
     * @param  array<string, string>  $replacements
     */
    private function replaceInFields(ServicePage|ContentPage $model, array $fields, array $replacements): void
    {
        foreach ($fields as $field) {
            $current = $model->getAttribute($field);

            if (! is_string($current) || $current === '') {
                continue;
            }

            $model->setAttribute($field, str_replace(array_keys($replacements), array_values($replacements), $current));
        }

        if ($model->isDirty()) {
            $model->save();
        }
    }
}
