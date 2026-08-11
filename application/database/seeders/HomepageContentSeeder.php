<?php

namespace Database\Seeders;

use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Models\HomepagePromotion;
use App\Modules\Content\Models\HomepageReview;
use Illuminate\Database\Seeder;

class HomepageContentSeeder extends Seeder
{
    public function run(): void
    {
        HomepagePromotion::query()->firstOrCreate(
            ['cta_url' => '/dekkskift-tromso'],
            [
                'sort_order' => 10,
                'publication_status' => ContentPageStatus::Published,
                'title_nb' => 'Dekkskift på verksted fra 550 kr',
                'body_nb' => 'Pris etter felgdiameter: til og med R15 fra 550 kr, R16–18 fra 600 kr og R19 eller større fra 650 kr.',
                'cta_label_nb' => 'Se dekkskift',
                'title_ru' => 'Замена колёс в мастерской от 550 крон',
                'body_ru' => 'Цена зависит от диаметра: до R15 — от 550 крон, R16–18 — от 600 крон, R19 и больше — от 650 крон.',
                'cta_label_ru' => 'Открыть услугу',
            ],
        );

        foreach ($this->reviews() as $review) {
            HomepageReview::query()->firstOrCreate(
                ['author_nb' => $review['author_nb']],
                $review,
            );
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function reviews(): array
    {
        $sourceUrl = config('storefront.facebook_url');

        return [
            [
                'sort_order' => 10,
                'publication_status' => ContentPageStatus::Published,
                'quote_nb' => 'Raskt og enkelt utført dekkskifte til en hyggelig pris. Presise og meget serviceinnstilte. Anbefales på det varmeste!',
                'author_nb' => 'Solveig Lundin',
                'source_label_nb' => 'Se omtaler på Facebook',
                'quote_ru' => 'Быстрая и простая замена колёс по приятной цене. Точные и очень внимательные сотрудники. Искренне рекомендую!',
                'author_ru' => 'Solveig Lundin',
                'source_label_ru' => 'Отзывы в Facebook',
                'source_url' => $sourceUrl,
            ],
            [
                'sort_order' => 20,
                'publication_status' => ContentPageStatus::Published,
                'quote_nb' => 'Dette var supereasy for meg! Hjulskift og sjekk av lufttrykk på no time. Anbefales!',
                'author_nb' => 'Sissel Sollied',
                'source_label_nb' => 'Se omtaler på Facebook',
                'quote_ru' => 'Для меня всё оказалось действительно очень просто: замена колёс и проверка давления заняли совсем немного времени. Рекомендую!',
                'author_ru' => 'Sissel Sollied',
                'source_label_ru' => 'Отзывы в Facebook',
                'source_url' => $sourceUrl,
            ],
            [
                'sort_order' => 30,
                'publication_status' => ContentPageStatus::Published,
                'quote_nb' => 'Tusen takk for en fantastisk god service! Jeg vil absolutt anbefale dere til alle som trenger å bytte dekk.',
                'author_nb' => 'Marit Næsvold',
                'source_label_nb' => 'Se omtaler på Facebook',
                'quote_ru' => 'Большое спасибо за великолепный сервис! Обязательно порекомендую вас всем, кому нужна замена колёс.',
                'author_ru' => 'Marit Næsvold',
                'source_label_ru' => 'Отзывы в Facebook',
                'source_url' => $sourceUrl,
            ],
        ];
    }
}
