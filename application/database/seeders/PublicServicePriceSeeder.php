<?php

namespace Database\Seeders;

use App\Modules\Commerce\Models\PublicServicePrice;
use Illuminate\Database\Seeder;

class PublicServicePriceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->prices() as $price) {
            PublicServicePrice::query()->firstOrCreate(['code' => $price['code']], $price);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function prices(): array
    {
        return [
            $this->price('workshop-r15', 'workshop', 'Dekkskift på verksted', 'Замена колёс в мастерской', 'Til og med R15', 'До R15 включительно', 55000, 110),
            $this->price('workshop-r16-r18', 'workshop', 'Dekkskift på verksted', 'Замена колёс в мастерской', 'R16–R18', 'R16–R18', 60000, 120),
            $this->price('workshop-r19', 'workshop', 'Dekkskift på verksted', 'Замена колёс в мастерской', 'R19 eller større', 'R19 и больше', 65000, 130, 'Gjelder ikke stor varebil eller bobil.', 'Не относится к крупным фургонам и автодомам.'),
            $this->price('mobile-r15', 'mobile', 'Dekkskift hjemme hos deg', 'Выездная замена колёс', 'Til og med R15', 'До R15 включительно', 65000, 210),
            $this->price('mobile-r16-r18', 'mobile', 'Dekkskift hjemme hos deg', 'Выездная замена колёс', 'R16–R18', 'R16–R18', 70000, 220),
            $this->price('mobile-r19', 'mobile', 'Dekkskift hjemme hos deg', 'Выездная замена колёс', 'R19 eller større', 'R19 и больше', 75000, 230),
            $this->price('mobile-large-vehicle', 'mobile', 'Dekkskift hjemme hos deg', 'Выездная замена колёс', 'Stor varebil eller bobil', 'Крупный фургон или автодом', 90000, 240),
            $this->price('mobile-six-wheels', 'mobile', 'Dekkskift hjemme hos deg', 'Выездная замена колёс', 'Varebil eller bobil med 6 hjul', 'Фургон или автодом с 6 колёсами', 120000, 250),
            $this->price('hotel-r15', 'hotel', 'Dekkhotell per sesong', 'Хранение колёс за сезон', 'Til og med R15', 'До R15 включительно', 75000, 310, 'Vask, luftkontroll og lagring i opptil 6 måneder. Dekkskift kommer i tillegg.', 'Мойка, проверка давления и хранение до 6 месяцев. Замена колёс оплачивается отдельно.'),
            $this->price('hotel-r16-r18', 'hotel', 'Dekkhotell per sesong', 'Хранение колёс за сезон', 'R16–R18', 'R16–R18', 85000, 320, 'Vask, luftkontroll og lagring i opptil 6 måneder. Dekkskift kommer i tillegg.', 'Мойка, проверка давления и хранение до 6 месяцев. Замена колёс оплачивается отдельно.'),
            $this->price('hotel-r19', 'hotel', 'Dekkhotell per sesong', 'Хранение колёс за сезон', 'R19 eller større', 'R19 и больше', 95000, 330, 'Vask, luftkontroll og lagring i opptil 6 måneder. Dekkskift kommer i tillegg.', 'Мойка, проверка давления и хранение до 6 месяцев. Замена колёс оплачивается отдельно.'),
        ];
    }

    /** @return array<string, mixed> */
    private function price(
        string $code,
        string $categoryCode,
        string $categoryNb,
        string $categoryRu,
        string $titleNb,
        string $titleRu,
        int $priceMinor,
        int $sortOrder,
        ?string $descriptionNb = null,
        ?string $descriptionRu = null,
    ): array {
        return [
            'code' => $code,
            'category_code' => $categoryCode,
            'category_title_nb' => $categoryNb,
            'category_title_ru' => $categoryRu,
            'title_nb' => $titleNb,
            'title_ru' => $titleRu,
            'description_nb' => $descriptionNb,
            'description_ru' => $descriptionRu,
            'price_minor' => $priceMinor,
            'currency' => 'NOK',
            'is_active' => true,
            'sort_order' => $sortOrder,
        ];
    }
}
