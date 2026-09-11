<?php

namespace Database\Seeders;

use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Enums\HomepageServiceCollection;
use App\Modules\Content\Models\HomepagePromotion;
use App\Modules\Content\Models\HomepageServiceCard;
use App\Modules\Content\Models\MediaAsset;
use App\Modules\Content\Models\ServicePage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Deterministic Issue #46 content manifest.
 *
 * Existing manifest rows, including soft-deleted rows, are never recreated or
 * overwritten. The legacy workshop offer receives a stable key, while any
 * operator-owned differences are preserved instead of being replaced.
 */
final class CustomerHomepageCollectionsSeeder extends Seeder
{
    public function run(): void
    {
        $assets = $this->seedApprovedMedia();

        foreach ($this->promotions($assets) as $promotion) {
            $promotion['manifest_hash'] = $this->manifestHash($promotion);
            $this->seedPromotion($promotion);
        }

        $services = ServicePage::query()->get()->keyBy('slug');
        foreach ($this->serviceCards($assets) as $card) {
            $serviceSlug = $card['service_slug'];
            unset($card['service_slug']);
            $card['service_page_id'] = $serviceSlug === null ? null : $services->get($serviceSlug)?->id;
            $card['manifest_hash'] = $this->manifestHash($card);

            HomepageServiceCard::query()->withTrashed()->firstOrCreate(
                ['content_key' => $card['content_key']],
                $card,
            );
        }
    }

    /** @param array<string, mixed> $payload */
    private function manifestHash(array $payload): string
    {
        $normalized = array_map(
            static fn (mixed $value): mixed => $value instanceof \BackedEnum ? $value->value : $value,
            $payload,
        );
        ksort($normalized);

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $promotion */
    private function seedPromotion(array $promotion): void
    {
        if (HomepagePromotion::query()->withTrashed()->where('content_key', $promotion['content_key'])->exists()) {
            return;
        }

        if ($promotion['content_key'] === 'issue-46-offer-workshop-wheel-change') {
            $legacy = HomepagePromotion::query()
                ->withTrashed()
                ->whereNull('content_key')
                ->where(function ($query): void {
                    $query
                        ->where('cta_url', '/dekkskift-tromso')
                        ->orWhere('title_nb', 'Dekkskift på verksted fra 550 kr')
                        ->orWhere('body_nb', 'Pris etter felgdiameter: til og med R15 fra 550 kr, R16–18 fra 600 kr og R19 eller større fra 650 kr.')
                        ->orWhere('title_ru', 'Замена колёс в мастерской от 550 крон')
                        ->orWhere('body_ru', 'Цена зависит от диаметра: до R15 — от 550 крон, R16–18 — от 600 крон, R19 и больше — от 650 крон.');
                })
                ->first();

            if ($legacy === null) {
                $unownedPromotions = HomepagePromotion::query()
                    ->withTrashed()
                    ->whereNull('content_key')
                    ->orderBy('id')
                    ->limit(2)
                    ->get();

                if ($unownedPromotions->count() === 1) {
                    // A fully edited legacy offer may no longer contain any of
                    // the original fixture strings. A sole unowned row is still
                    // safe to adopt because its public/operator fields are kept.
                    $legacy = $unownedPromotions->first();
                } elseif ($unownedPromotions->count() > 1) {
                    // Never guess between multiple operator-owned records and
                    // never add a possibly duplicate workshop offer beside them.
                    Log::warning('Issue #46 workshop promotion adoption is ambiguous; default offer was not created.', [
                        'unowned_promotion_ids' => $unownedPromotions->pluck('id')->all(),
                    ]);

                    return;
                }
            }

            if ($legacy !== null) {
                $conflictingFields = collect($this->legacyWorkshopBaseline())
                    ->filter(function (mixed $expected, string $field) use ($legacy): bool {
                        $actual = $legacy->getAttribute($field);
                        if ($actual instanceof \BackedEnum) {
                            $actual = $actual->value;
                        }

                        return $actual !== $expected;
                    })
                    ->keys()
                    ->values()
                    ->all();

                $legacy->content_key = $promotion['content_key'];
                if ($conflictingFields === []) {
                    $legacy->fill($promotion);
                } else {
                    Log::warning('Issue #46 workshop promotion adoption preserved operator-owned fields.', [
                        'promotion_id' => $legacy->getKey(),
                        'conflicting_fields' => $conflictingFields,
                    ]);
                }
                $legacy->save();

                return;
            }
        }

        HomepagePromotion::query()->create($promotion);
    }

    /** @return array<string, mixed> */
    private function legacyWorkshopBaseline(): array
    {
        return [
            'sort_order' => 10,
            'publication_status' => ContentPageStatus::Published->value,
            'title_nb' => 'Dekkskift på verksted fra 550 kr',
            'short_body_nb' => null,
            'body_nb' => 'Pris etter felgdiameter: til og med R15 fra 550 kr, R16–18 fra 600 kr og R19 eller større fra 650 kr.',
            'cta_label_nb' => 'Se dekkskift',
            'title_ru' => 'Замена колёс в мастерской от 550 крон',
            'short_body_ru' => null,
            'body_ru' => 'Цена зависит от диаметра: до R15 — от 550 крон, R16–18 — от 600 крон, R19 и больше — от 650 крон.',
            'cta_label_ru' => 'Открыть услугу',
            'cta_url' => '/dekkskift-tromso',
            'media_asset_id' => null,
        ];
    }

    /** @return array<string, string> */
    private function seedApprovedMedia(): array
    {
        $definitions = [
            'mobile-service' => ['file' => 'mobile-service.webp', 'alt_nb' => 'EasyTire utfører arbeid ved bilen', 'alt_ru' => 'EasyTire выполняет работу у автомобиля'],
            'workshop' => ['file' => 'workshop.webp', 'alt_nb' => 'EasyTire-verkstedet i Tromsø', 'alt_ru' => 'Мастерская EasyTire в Тромсё'],
            'workshop-alt' => ['file' => 'workshop-alt.webp', 'alt_nb' => 'Arbeidsområde i EasyTire-verkstedet', 'alt_ru' => 'Рабочая зона мастерской EasyTire'],
            'dekkhotell' => ['file' => 'dekkhotell.webp', 'alt_nb' => 'Hjul lagret hos EasyTire', 'alt_ru' => 'Колёса на хранении в EasyTire'],
            'exterior' => ['file' => 'exterior.webp', 'alt_nb' => 'EasyTire-biler ved verkstedet', 'alt_ru' => 'Автомобили EasyTire у мастерской'],
        ];

        $assets = [];
        foreach ($definitions as $key => $definition) {
            $sourcePath = public_path('brand/'.$definition['file']);
            $bytes = is_file($sourcePath) ? file_get_contents($sourcePath) : false;
            $dimensions = $bytes === false ? false : getimagesizefromstring($bytes);
            if ($bytes === false || $dimensions === false) {
                throw new LogicException('Approved Issue #46 media is unavailable: '.$definition['file']);
            }

            $storagePath = 'media/homepage/issue-46/'.$definition['file'];
            $asset = MediaAsset::query()->firstOrNew(['path' => $storagePath]);
            if (! $asset->exists) {
                Storage::disk('public')->put($storagePath, $bytes);
                $asset->fill([
                    'disk' => 'public',
                    'original_name' => 'issue-46-'.$definition['file'],
                    'mime' => 'image/webp',
                    'byte_size' => strlen($bytes),
                    'width' => $dimensions[0],
                    'height' => $dimensions[1],
                    'sha256' => hash('sha256', $bytes),
                    'alt_nb' => $definition['alt_nb'],
                    'alt_ru' => $definition['alt_ru'],
                    'uploader_id' => null,
                    'is_active' => true,
                ])->save();
            }

            $assets[$key] = $asset->id;
        }

        return $assets;
    }

    /** @param array<string, string> $assets
     * @return list<array<string, mixed>>
     */
    private function promotions(array $assets): array
    {
        return [
            [
                'content_key' => 'issue-46-offer-early-booking', 'sort_order' => 10, 'publication_status' => ContentPageStatus::Published,
                'title_nb' => 'Bestill i god tid – spar opptil 100 kr', 'short_body_nb' => 'Bestill dekkskift hjemme før 25. september.',
                'body_nb' => 'Få 50 kr rabatt på dekkskift hjemme hos deg, eller 100 kr total rabatt når du bestiller dekkskift hjemme sammen med dekkhotell. Rabattene summeres ikke – ved bestilling av begge tjenestene er samlet rabatt 100 kr.', 'cta_label_nb' => 'Bestill nå',
                'title_ru' => 'Закажите заранее — сэкономьте до 100 крон', 'short_body_ru' => 'Закажите замену колёс на дому до 25 сентября.',
                'body_ru' => 'Получите скидку 50 крон на замену колёс у вас или общую скидку 100 крон при одновременном заказе выездной замены колёс и шинного отеля. Скидки не суммируются: при заказе обеих услуг общая скидка составляет 100 крон.', 'cta_label_ru' => 'Заказать сейчас',
                'cta_url' => '/tjenester/mobilt-dekkskift/foresporsel?fulfilment=mobile', 'media_asset_id' => $assets['mobile-service'],
            ],
            [
                'content_key' => 'issue-46-offer-multiple-cars', 'sort_order' => 20, 'publication_status' => ContentPageStatus::Published,
                'title_nb' => 'Flere biler på samme adresse', 'short_body_nb' => 'Spar opptil 100 kr per bil.',
                'body_nb' => 'Ved dekkskift hjemme på samme adresse får dere 50 kr rabatt per bil ved to biler, og 100 kr rabatt per bil ved tre eller flere biler.', 'cta_label_nb' => 'Bestill for flere biler',
                'title_ru' => 'Несколько автомобилей по одному адресу', 'short_body_ru' => 'Сэкономьте до 100 крон на каждом автомобиле.',
                'body_ru' => 'При выездной замене колёс по одному адресу скидка составляет 50 крон на каждый автомобиль при двух автомобилях и 100 крон на каждый при трёх и более.', 'cta_label_ru' => 'Заказать для нескольких автомобилей',
                'cta_url' => '/tjenester/mobilt-dekkskift/foresporsel?fulfilment=mobile', 'media_asset_id' => $assets['exterior'],
            ],
            [
                'content_key' => 'issue-46-offer-hotel-collection', 'sort_order' => 30, 'publication_status' => ContentPageStatus::Published,
                'title_nb' => 'Gratis henting til dekkhotellet', 'short_body_nb' => 'Vi henter hjulene dine kostnadsfritt fra 1. til 20. september.',
                'body_nb' => 'Fra 1. til 20. september henter vi hjulene hjemme hos deg eller på et annet avtalt sted og frakter dem kostnadsfritt til dekkhotellet vårt.', 'cta_label_nb' => 'Bestill henting',
                'title_ru' => 'Бесплатная доставка в шинный отель', 'short_body_ru' => 'С 1 по 20 сентября мы бесплатно заберём ваши колёса.',
                'body_ru' => 'С 1 по 20 сентября мы заберём колёса у вас дома или в другом согласованном месте и бесплатно доставим их в наш шинный отель.', 'cta_label_ru' => 'Заказать перевозку',
                'cta_url' => '/tjenester/dekkhotell/foresporsel?fulfilment=mobile', 'media_asset_id' => $assets['dekkhotell'],
            ],
            [
                'content_key' => 'issue-46-offer-workshop-wheel-change', 'sort_order' => 40, 'publication_status' => ContentPageStatus::Published,
                'title_nb' => 'Dekkskift på verksted – kun fra 550 kr', 'short_body_nb' => 'Raskt og enkelt på verkstedet vårt i Ringvegen 200.',
                'body_nb' => 'Pris etter felgdiameter: til og med R15 fra 550 kr, R16–R18 fra 600 kr og R19 eller større fra 650 kr.', 'cta_label_nb' => 'Bestill dekkskift',
                'title_ru' => 'Замена колёс в мастерской — от 550 крон', 'short_body_ru' => 'Быстро и удобно в нашей мастерской по адресу Ringvegen 200.',
                'body_ru' => 'Цена зависит от диаметра диска: до R15 включительно — от 550 крон, R16–R18 — от 600 крон, R19 и больше — от 650 крон.', 'cta_label_ru' => 'Заказать замену колёс',
                'cta_url' => '/tjenester/mobilt-dekkskift/foresporsel?fulfilment=workshop', 'media_asset_id' => $assets['workshop-alt'],
            ],
        ];
    }

    /** @param array<string, string> $assets
     * @return list<array<string, mixed>>
     */
    private function serviceCards(array $assets): array
    {
        $published = ContentPageStatus::Published;

        return [
            $this->card('additional-oil-change', HomepageServiceCollection::Additional, 10, 'oljeskift', 'Oljeskift', 'Oljeskift på verkstedet eller hjemme hos deg. Vi bruker olje og filter som passer bilen din.', 'Les mer', 'Замена масла', 'Замена масла в мастерской или у вас. Используем масло и фильтр, подходящие вашему автомобилю.', 'Подробнее', null, $assets['workshop'], $published),
            $this->card('additional-interior-cleaning', HomepageServiceCollection::Additional, 20, null, 'Interiørrens', 'Fra enkel innvendig vask til grundig interiørrens av hele bilen.', 'Les mer', 'Химчистка салона', 'От простой уборки салона до глубокой химчистки всего автомобиля.', 'Подробнее', '/tjenester#service-interior-cleaning', $assets['exterior'], $published),
            $this->card('additional-polishing-paint', HomepageServiceCollection::Additional, 30, null, 'Polering og lakkorrigering', 'Polering og lakkorrigering som gir lakken tilbake glans og en jevnere overflate.', 'Les mer', 'Полировка и коррекция лакокрасочного покрытия', 'Полировка и коррекция, которые возвращают покрытию блеск и делают поверхность ровнее.', 'Подробнее', '/tjenester#service-polishing-paint', $assets['workshop-alt'], $published),
            $this->card('additional-lockout', HomepageServiceCollection::Additional, 40, null, 'Utelåsing – åpning av bil', 'Har du låst nøklene inne i bilen? Vi kommer til deg og hjelper deg med å åpne bildøren på en skånsom måte.', 'Bestill hjelp', 'Открытие заблокированного автомобиля', 'Оставили ключи внутри? Мы приедем и поможем аккуратно открыть дверь автомобиля.', 'Заказать помощь', '/kontakt', $assets['mobile-service'], $published),
            $this->card('additional-lights-wipers', HomepageServiceCollection::Additional, 50, 'lyspaerer-viskerblad', 'Bytte av lyspærer og vindusviskere', 'Vi bytter lyspærer og vindusviskere raskt og enkelt.', 'Les mer', 'Замена ламп и стеклоочистителей', 'Быстро и просто заменим автомобильные лампы и стеклоочистители.', 'Подробнее', null, $assets['workshop-alt'], $published),
            $this->card('additional-battery', HomepageServiceCollection::Additional, 60, 'bilbatteri', 'Batteritest og batteribytte', 'Vi kontrollerer batteriet og kan skaffe og bytte det ved behov.', 'Les mer', 'Проверка и замена аккумулятора', 'Проверим аккумулятор и при необходимости подберём и заменим его.', 'Подробнее', null, $assets['exterior'], $published),

            $this->card('wheels-mobile-change', HomepageServiceCollection::Wheels, 10, 'mobilt-dekkskift', 'Dekkskift hjemme hos deg', 'Vi kommer hjem til deg eller til arbeidsplassen og skifter hjulene der bilen står.', 'Les mer', 'Замена колёс у вас', 'Приедем домой или на работу и заменим колёса там, где стоит автомобиль.', 'Подробнее', null, $assets['mobile-service'], $published),
            $this->card('wheels-hotel', HomepageServiceCollection::Wheels, 20, 'dekkhotell', 'Dekkhotell i Tromsø', 'Vi oppbevarer hjulene dine trygt og kan ta oss av hele sesongskiftet.', 'Les mer', 'Шинный отель в Тромсё', 'Безопасно храним колёса и можем взять на себя весь сезонный цикл.', 'Подробнее', null, $assets['dekkhotell'], $published),
            $this->card('wheels-workshop-change', HomepageServiceCollection::Wheels, 30, 'mobilt-dekkskift', 'Dekkskift på verksted', 'Bestill tid og få hjulene skiftet på verkstedet vårt i Ringvegen 200.', 'Les mer', 'Замена колёс в мастерской', 'Запишитесь на замену колёс в нашей мастерской по адресу Ringvegen 200.', 'Подробнее', '/tjenester/mobilt-dekkskift/foresporsel?fulfilment=workshop', $assets['workshop-alt'], $published),
            $this->card('wheels-products', HomepageServiceCollection::Wheels, 40, null, 'Dekk, felg og hjulpakker', 'Finn dekk, felger eller komplette hjulpakker som passer bilen din.', 'Se produkter', 'Шины, диски и колёсные комплекты', 'Найдите подходящие автомобилю шины, диски или готовые колёсные комплекты.', 'Посмотреть товары', '/dekk-og-felger', $assets['workshop'], $published),
            $this->card('wheels-tesla-ev', HomepageServiceCollection::Wheels, 50, 'tesla-og-elbil', 'Dekkskift for Tesla og elbil', 'Dekkskift for Tesla og andre elbiler med riktig utstyr og riktige løftepunkter.', 'Les mer', 'Замена колёс для Tesla и электромобилей', 'Меняем колёса на Tesla и других электромобилях с подходящим оборудованием и правильными точками подъёма.', 'Подробнее', null, $assets['mobile-service'], $published),
            $this->card('wheels-motorhome-van', HomepageServiceCollection::Wheels, 60, 'bobil-og-varebil', 'Bobil og varebil', 'Hjulskift for varebiler og bobiler med egnet utstyr.', 'Les mer', 'Автодома и фургоны', 'Замена колёс на фургонах и автодомах с использованием подходящего оборудования.', 'Подробнее', null, $assets['exterior'], $published),
        ];
    }

    /** @return array<string, mixed> */
    private function card(
        string $key,
        HomepageServiceCollection $collection,
        int $order,
        ?string $serviceSlug,
        string $titleNb,
        string $descriptionNb,
        string $ctaNb,
        string $titleRu,
        string $descriptionRu,
        string $ctaRu,
        ?string $ctaUrl,
        string $mediaAssetId,
        ContentPageStatus $status,
    ): array {
        return [
            'content_key' => 'issue-46-'.$key,
            'collection' => $collection,
            'service_slug' => $serviceSlug,
            'sort_order' => $order,
            'publication_status' => $status,
            'title_nb' => $titleNb,
            'description_nb' => $descriptionNb,
            'cta_label_nb' => $ctaNb,
            'title_ru' => $titleRu,
            'description_ru' => $descriptionRu,
            'cta_label_ru' => $ctaRu,
            'cta_url' => $ctaUrl,
            'media_asset_id' => $mediaAssetId,
        ];
    }
}
