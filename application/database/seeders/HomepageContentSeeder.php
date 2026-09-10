<?php

namespace Database\Seeders;

use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Enums\ReviewPlatform;
use App\Modules\Content\Models\HomepagePromotion;
use App\Modules\Content\Models\HomepageRatingSummary;
use App\Modules\Content\Models\HomepageReview;
use App\Support\PublicHttpUrl;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class HomepageContentSeeder extends Seeder
{
    public function run(): void
    {
        $hasIssue46WorkshopOffer = Schema::hasColumn('homepage_promotions', 'content_key')
            && HomepagePromotion::query()->withTrashed()->where('content_key', 'issue-46-offer-workshop-wheel-change')->exists();

        if (! $hasIssue46WorkshopOffer) {
            HomepagePromotion::query()->firstOrCreate(
                ['cta_url' => '/dekkskift-tromso'],
                [
                    'sort_order' => 10,
                    'publication_status' => ContentPageStatus::Published,
                    'title_nb' => 'Dekkskift på verksted fra 550 kr',
                    'short_body_nb' => 'Raskt og enkelt på verkstedet vårt i Ringvegen 200.',
                    'body_nb' => 'Pris etter felgdiameter: til og med R15 fra 550 kr, R16–18 fra 600 kr og R19 eller større fra 650 kr.',
                    'cta_label_nb' => 'Se dekkskift',
                    'title_ru' => 'Замена колёс в мастерской от 550 крон',
                    'short_body_ru' => 'Быстро и удобно в нашей мастерской по адресу Ringvegen 200.',
                    'body_ru' => 'Цена зависит от диаметра: до R15 — от 550 крон, R16–18 — от 600 крон, R19 и больше — от 650 крон.',
                    'cta_label_ru' => 'Открыть услугу',
                ],
            );
        }

        foreach ($this->reviews() as $review) {
            HomepageReview::query()->firstOrCreate(
                ['author_nb' => $review['author_nb']],
                $review,
            );
        }

        foreach ($this->ratingSummaries() as $summary) {
            HomepageRatingSummary::query()->firstOrCreate(
                ['platform' => $summary['platform']],
                $summary,
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

    /** @return array<int, array<string, mixed>> */
    private function ratingSummaries(): array
    {
        $facebookUrl = PublicHttpUrl::sanitize(config('storefront.facebook_url'));
        $googleUrl = PublicHttpUrl::sanitize(config('storefront.map_url'));

        return [
            [
                'platform' => ReviewPlatform::Facebook,
                'rating' => '5.0',
                'review_count' => 30,
                'count_label_nb' => '30+ omtaler',
                'count_label_ru' => '30+ отзывов',
                'link_label_nb' => 'Se alle omtaler på Facebook',
                'link_label_ru' => 'Все отзывы в Facebook',
                'source_url' => $facebookUrl,
                'sort_order' => 10,
                'publication_status' => $facebookUrl ? ContentPageStatus::Published : ContentPageStatus::Draft,
            ],
            [
                'platform' => ReviewPlatform::Google,
                'rating' => '5.0',
                'review_count' => 30,
                'count_label_nb' => 'Omtrent 30 omtaler',
                'count_label_ru' => 'Около 30 отзывов',
                'link_label_nb' => 'Se alle omtaler på Google',
                'link_label_ru' => 'Все отзывы в Google',
                'source_url' => $googleUrl,
                'sort_order' => 20,
                'publication_status' => $googleUrl ? ContentPageStatus::Published : ContentPageStatus::Draft,
            ],
        ];
    }
}
