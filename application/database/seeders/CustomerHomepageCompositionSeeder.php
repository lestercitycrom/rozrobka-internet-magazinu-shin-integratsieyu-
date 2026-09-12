<?php

namespace Database\Seeders;

use App\Modules\Commerce\Models\PublicServicePrice;
use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Models\HomepageBusinessPartner;
use App\Modules\Content\Models\MediaAsset;
use App\Modules\Content\Models\StorefrontSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Applies the approved Issue #47 homepage copy and locally owned partner media.
 * Existing operator changes are never guessed at or overwritten.
 */
final class CustomerHomepageCompositionSeeder extends Seeder
{
    public const HOTEL_PRICE_MANIFEST = '12df3aa5a8f640785557d9a901f79b9aa7a38718a9957913dc026f243d0bc55b';

    public const RELEASE = 'issue-47-v1';

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedSettings();
            $this->seedHotelPrice();
            $assets = $this->seedPartnerMedia();

            foreach ($this->partners($assets) as $partner) {
                $this->seedPartner($partner);
            }
        });
    }

    private function seedSettings(): void
    {
        $settings = StorefrontSettings::query()->find(1) ?? new StorefrontSettings(['id' => 1]);

        $desired = [
            'logistics_kicker_nb' => 'DEKKHOTELL',
            'logistics_title_nb' => 'Dekkhotell uten stress',
            'hotel_kicker_nb' => 'DEKKHOTELL',
            'hotel_title_nb' => 'Trygg lagring mellom sesongene.',
            'hotel_text_nb' => 'Vi oppbevarer hjulene dine trygt, vasker dem og kontrollerer tilstanden før neste sesong.',
            'hotel_cta_nb' => 'Les om dekkhotell',
            'mobile_kicker_nb' => 'HJEMME HOS DEG',
            'mobile_title_nb' => 'Vi ordner hele hjulskiftet.',
            'mobile_text_nb' => 'Vi kommer med hjulene dine, skifter dem der bilen står og tar med det andre settet tilbake til dekkhotellet.',
            'mobile_cta_nb' => 'Se hvordan det fungerer',
            'mobile_details_title_nb' => 'Dekkhotell uten stress',
            'mobile_details_body_1_nb' => 'Med dekkhotell hos EasyTire slipper du å løfte, frakte eller oppbevare hjulene selv. Når det er tid for sesongskifte, tar vi med riktig hjulsett til bilen din, skifter hjulene på stedet og tar med det andre settet tilbake til dekkhotellet.',
            'mobile_details_body_2_nb' => 'Er dekkene utslitt, kan vi skaffe og montere nye dekk mens hjulene står hos oss. De er da klare til neste dekkskift.',
            'process_kicker_nb' => 'DEKKSKIFT HJEMME HOS DEG',
            'process_title_nb' => 'Slik fungerer det',
            'process_lead_nb' => 'En forespørsel er først en avtale når EasyTire har bekreftet tidspunktet.',
            'process_1_title_nb' => 'Bestill tid og sted',
            'process_1_text_nb' => 'Velg ønsket tidspunkt og oppgi hvor bilen står.',
            'process_2_title_nb' => 'Vi bekrefter avtalen',
            'process_2_text_nb' => 'Vi bekrefter tidspunktet og praktiske detaljer.',
            'process_3_title_nb' => 'Vi kommer til deg',
            'process_3_text_nb' => 'Vi tar med nødvendig utstyr og skifter hjulene der bilen står. Det tar normalt rundt 20 minutter.',
            'process_4_title_nb' => 'Betal etterpå',
            'process_4_text_nb' => 'Du betaler først når arbeidet er utført.',
            'process_cta_nb' => 'Bestill nå!',
            'contact_kicker_nb' => 'KONTAKT',
            'contact_title_nb' => 'Vil du snakke med oss?',
            'contact_text_nb' => 'Ring oss, send en e-post eller finn veien til verkstedet vårt i Google Maps.',
            'contact_map_note_nb' => 'Google Maps viser riktig innkjøring via Ringvegen 202.',
            'business_kicker_nb' => 'BEDRIFT',
            'business_title_nb' => 'En lokal partner for bedrifter med bilpark',
            'business_text_nb' => 'EasyTire hjelper lokale bedrifter med dekkskift på arbeidsplassen, dekkhotell og tilpasset oppfølging av bilparken.',
            'business_cta_nb' => 'Be om et tilbud',
            'business_partners_heading_nb' => 'Noen av bedriftene vi hjelper',

            'logistics_kicker_ru' => 'ШИННЫЙ ОТЕЛЬ',
            'logistics_title_ru' => 'Шинный отель без хлопот',
            'hotel_kicker_ru' => 'ШИННЫЙ ОТЕЛЬ',
            'hotel_title_ru' => 'Надёжное хранение между сезонами.',
            'hotel_text_ru' => 'Мы безопасно храним ваши колёса, моем их и проверяем состояние перед следующим сезоном.',
            'hotel_cta_ru' => 'Подробнее о шинном отеле',
            'mobile_kicker_ru' => 'У ВАС',
            'mobile_title_ru' => 'Берём на себя всю сезонную замену колёс.',
            'mobile_text_ru' => 'Мы привезём ваши колёса, заменим их там, где стоит автомобиль, и заберём второй комплект обратно в шинный отель.',
            'mobile_cta_ru' => 'Посмотреть, как это работает',
            'mobile_details_title_ru' => 'Шинный отель без хлопот',
            'mobile_details_body_1_ru' => 'С шинным отелем EasyTire вам не придётся самостоятельно поднимать, перевозить или хранить колёса. Когда наступит время сезонной замены, мы привезём нужный комплект к вашему автомобилю, заменим колёса на месте и заберём второй комплект обратно в шинный отель.',
            'mobile_details_body_2_ru' => 'Если шины изношены, мы можем заказать и установить новые, пока колёса находятся у нас. К следующей сезонной замене они будут готовы.',
            'process_kicker_ru' => 'ЗАМЕНА КОЛЁС У ВАС',
            'process_title_ru' => 'Как это работает',
            'process_lead_ru' => 'Заявка становится записью только после подтверждения времени EasyTire.',
            'process_1_title_ru' => 'Выберите время и место',
            'process_1_text_ru' => 'Выберите желаемое время и укажите, где находится автомобиль.',
            'process_2_title_ru' => 'Мы подтверждаем запись',
            'process_2_text_ru' => 'Мы подтверждаем время и практические детали.',
            'process_3_title_ru' => 'Мы приедем к вам',
            'process_3_text_ru' => 'Мы привезём необходимое оборудование и заменим колёса там, где стоит автомобиль. Обычно это занимает около 20 минут.',
            'process_4_title_ru' => 'Оплатите после выполнения',
            'process_4_text_ru' => 'Вы платите только после выполнения работы.',
            'process_cta_ru' => 'Заказать сейчас!',
            'contact_kicker_ru' => 'КОНТАКТЫ',
            'contact_title_ru' => 'Хотите поговорить с нами?',
            'contact_text_ru' => 'Позвоните, напишите нам или проложите маршрут до нашей мастерской в Google Maps.',
            'contact_map_note_ru' => 'Google Maps показывает правильный въезд через Ringvegen 202.',
            'business_kicker_ru' => 'ДЛЯ БИЗНЕСА',
            'business_title_ru' => 'Локальный партнёр для компаний с автопарком',
            'business_text_ru' => 'EasyTire помогает местным компаниям с заменой колёс на рабочем месте, шинным отелем и индивидуальным сопровождением автопарка.',
            'business_cta_ru' => 'Запросить предложение',
            'business_partners_heading_ru' => 'Некоторые компании, которым мы помогаем',
        ];

        $manifest = $settings->customer_homepage_composition_manifest;
        if (is_array($manifest) && ($manifest['release'] ?? null) === self::RELEASE) {
            return;
        }

        $prior = $this->priorSettingsValues();
        $changes = [];
        foreach ($desired as $field => $value) {
            $current = $settings->getAttribute($field);
            if ($current !== $value && (blank($current) || in_array($current, $prior[$field] ?? [], true))) {
                $changes[$field] = ['original' => $current, 'applied' => $value];
                $settings->setAttribute($field, $value);
            }
        }

        $settings->setAttribute('customer_homepage_composition_manifest', [
            'release' => self::RELEASE,
            'state' => 'applied',
            'changes' => $changes,
        ]);
        $settings->save();
    }

    /** @return array<string, list<string>> */
    private function priorSettingsValues(): array
    {
        return [
            'logistics_kicker_nb' => ['Dekkhotell · Dekkskift hjemme hos deg'], 'logistics_title_nb' => ['Dekkhotell', 'Hvor passer det best?'],
            'hotel_kicker_nb' => ['Dekkhotell'], 'hotel_title_nb' => ['Lagring koblet til sesongskiftet.'], 'hotel_text_nb' => ['Se tjenesten for betingelser. Pris og vilkår bekreftes av EasyTire før avtalen inngås.'],
            'mobile_kicker_nb' => ['Hjemme hos deg'], 'mobile_title_nb' => ['Vi kommer dit bilen står.'], 'mobile_text_nb' => ['Velg dekkskift hjemme hos deg og oppgi adressen. Tid og gjennomføring bekreftes av EasyTire.'], 'mobile_cta_nb' => ['Start en forespørsel'],
            'process_kicker_nb' => ['Slik fungerer det'], 'process_title_nb' => ['Slik foregår et hjulskift med EasyTire.'], 'process_lead_nb' => ['En forespørsel er først en avtale når EasyTire har bekreftet tidspunktet.'],
            'process_1_title_nb' => ['Velg sted og ønsket tid'], 'process_1_text_nb' => ['Bestill hjemme, på jobb eller ved verkstedet, og oppgi bilen det gjelder.'],
            'process_2_title_nb' => ['Vi bekrefter avtalen'], 'process_2_text_nb' => ['EasyTire kontakter deg og bekrefter tidspunkt, sted og praktiske detaljer.'],
            'process_3_title_nb' => ['Vi utfører arbeidet'], 'process_3_text_nb' => ['Teamet møter opp med nødvendig utstyr. Et vanlig hjulskift tar normalt rundt 20 minutter.'],
            'process_4_title_nb' => ['Betal etter utført arbeid'], 'process_4_text_nb' => ['Betaling skjer etter at arbeidet er utført og den endelige prisen er avklart.'],
            'contact_kicker_nb' => ['Kontakt'], 'contact_title_nb' => ['Trenger du et menneske først?'], 'contact_text_nb' => ['Ring, send e-post eller åpne det eksakte EasyTire-punktet i Google Maps.'],
            'business_kicker_nb' => ['Bedrift'], 'business_title_nb' => ['En lokal partner for bilparken.'], 'business_text_nb' => ['EasyTire samarbeider med lokale bedrifter og tilpasser hjulservice, lagring og oppfølging til bilparken.'],
            'logistics_kicker_ru' => ['Хранение колёс · Выездной сервис в Тромсё'], 'logistics_title_ru' => ['Хранение колёс', 'Где удобнее?'],
            'hotel_kicker_ru' => ['Хранение колёс'], 'hotel_title_ru' => ['Хранение вместе с сезонной заменой.'], 'hotel_text_ru' => ['Условия и стоимость EasyTire подтверждает до заключения соглашения.'], 'hotel_cta_ru' => ['О хранении колёс'],
            'mobile_kicker_ru' => ['Выездной сервис'], 'mobile_title_ru' => ['Приедем туда, где стоит автомобиль.'], 'mobile_text_ru' => ['Выберите выездную услугу и укажите адрес. Время и проведение работ подтверждает EasyTire.'], 'mobile_cta_ru' => ['Начать заявку'],
            'process_kicker_ru' => ['Как это работает'], 'process_title_ru' => ['Как проходит замена колёс с EasyTire.'], 'process_lead_ru' => ['Заявка становится записью только после подтверждения времени EasyTire.'],
            'process_1_title_ru' => ['Выберите место и желаемое время'], 'process_1_text_ru' => ['Укажите обслуживание дома, на работе или в мастерской и сообщите данные автомобиля.'],
            'process_2_title_ru' => ['Мы подтверждаем запись'], 'process_2_text_ru' => ['EasyTire связывается с вами и подтверждает время, место и практические детали.'],
            'process_3_title_ru' => ['Мы выполняем работу'], 'process_3_text_ru' => ['Команда приезжает с необходимым оборудованием. Обычная замена колёс занимает около 20 минут.'],
            'process_4_title_ru' => ['Оплата после выполнения'], 'process_4_text_ru' => ['Оплата производится после выполнения работы и согласования окончательной стоимости.'],
            'contact_kicker_ru' => ['Контакты'], 'contact_title_ru' => ['Сначала нужен человек?'], 'contact_text_ru' => ['Позвоните, напишите или откройте точку EasyTire в Google Maps.'],
            'business_kicker_ru' => ['Для бизнеса'], 'business_title_ru' => ['Локальный партнёр для корпоративного автопарка.'], 'business_text_ru' => ['EasyTire работает с местными компаниями и адаптирует шинный сервис, хранение и сопровождение под их автопарк.'],
        ];
    }

    private function seedHotelPrice(): void
    {
        $price = PublicServicePrice::query()->where('code', 'hotel-r15')->first();
        if ($price === null || $price->currency !== 'NOK') {
            return;
        }

        $manifest = $price->customer_homepage_composition_manifest;
        if (is_array($manifest) && ($manifest['release'] ?? null) === self::RELEASE) {
            return;
        }

        $changes = [];
        if ($price->price_minor === 75000) {
            $changes['price_minor'] = ['original' => 75000, 'applied' => 70000];
            $price->price_minor = 70000;
            $price->manifest_hash = self::HOTEL_PRICE_MANIFEST;
        }

        $price->customer_homepage_composition_manifest = [
            'release' => self::RELEASE,
            'state' => 'applied',
            'changes' => $changes,
        ];
        $price->save();
    }

    /** @return array<string, string> */
    private function seedPartnerMedia(): array
    {
        $assets = [];
        foreach (['nordic-spas.png', 'element-nor.png', 'carsoe.png', 'avis.png'] as $file) {
            $sourcePath = public_path('brand/partners/'.$file);
            $bytes = is_file($sourcePath) ? file_get_contents($sourcePath) : false;
            $dimensions = $bytes === false ? false : getimagesizefromstring($bytes);
            if ($bytes === false || $dimensions === false) {
                throw new LogicException('Approved Issue #47 partner logo is unavailable: '.$file);
            }

            $storagePath = 'media/homepage/issue-47/'.$file;
            $asset = MediaAsset::query()->firstOrNew(['path' => $storagePath]);
            if (! $asset->exists) {
                Storage::disk('public')->put($storagePath, $bytes);
                $asset->fill([
                    'disk' => 'public', 'original_name' => 'issue-47-'.$file, 'mime' => 'image/png',
                    'byte_size' => strlen($bytes), 'width' => $dimensions[0], 'height' => $dimensions[1],
                    'sha256' => hash('sha256', $bytes), 'alt_nb' => null, 'alt_ru' => null,
                    'uploader_id' => null, 'is_active' => true,
                ])->save();
            }
            $assets[$file] = $asset->id;
        }

        return $assets;
    }

    /** @param array<string, string> $assets @return list<array<string, mixed>> */
    private function partners(array $assets): array
    {
        return [
            ['content_key' => 'issue-47-nordic-spas', 'name' => 'Nordic Spas', 'media_asset_id' => $assets['nordic-spas.png'], 'website_url' => 'https://www.nordicspas.no/', 'sort_order' => 10, 'publication_status' => ContentPageStatus::Published],
            ['content_key' => 'issue-47-element-nor', 'name' => 'Element NOR', 'media_asset_id' => $assets['element-nor.png'], 'website_url' => 'https://elementnor.no/', 'sort_order' => 20, 'publication_status' => ContentPageStatus::Published],
            ['content_key' => 'issue-47-carsoe', 'name' => 'Carsoe', 'media_asset_id' => $assets['carsoe.png'], 'website_url' => 'https://carsoe.com/', 'sort_order' => 30, 'publication_status' => ContentPageStatus::Published],
            ['content_key' => 'issue-47-avis', 'name' => 'Avis', 'media_asset_id' => $assets['avis.png'], 'website_url' => 'https://www.avis.no/', 'sort_order' => 40, 'publication_status' => ContentPageStatus::Published],
        ];
    }

    /** @param array<string, mixed> $payload */
    private function seedPartner(array $payload): void
    {
        $hash = $this->manifestHash($payload);
        $existing = HomepageBusinessPartner::query()->withTrashed()->where('content_key', $payload['content_key'])->first();
        if ($existing !== null) {
            if (! $existing->trashed() && $existing->manifest_hash !== null && $this->manifestHash($existing->only(array_keys($payload))) === $existing->manifest_hash) {
                $existing->fill([...$payload, 'manifest_hash' => $hash])->save();
            }

            return;
        }

        $legacy = HomepageBusinessPartner::query()->withTrashed()
            ->whereNull('content_key')
            ->where(function ($query) use ($payload): void {
                $query->where('website_url', $payload['website_url'])->orWhere('name', $payload['name']);
            })
            ->first();
        if ($legacy !== null) {
            $legacy->content_key = $payload['content_key'];
            $legacy->manifest_hash = null;
            $legacy->save();

            return;
        }

        HomepageBusinessPartner::query()->create([...$payload, 'manifest_hash' => $hash]);
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
}
