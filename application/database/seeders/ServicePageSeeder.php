<?php

namespace Database\Seeders;

use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Enums\ServiceRequestFlow;
use App\Modules\Content\Models\ServicePage;
use Illuminate\Database\Seeder;

/** Initial editorial baseline. Existing pages are intentionally never overwritten. */
class ServicePageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $page) {
            ServicePage::query()->firstOrCreate(['slug' => $page['slug']], $page);
        }
    }

    /** @return list<array<string, string|int|ServicePagePublicationStatus|ServiceRequestFlow>> */
    private function pages(): array
    {
        return [
            $this->page('mobilt-dekkskift', 10, 'Dekkskift hjemme hos deg', 'Dekkskift der bilen står, etter avtale.', 'Vi planlegger et dekkskift ut fra biltype, hjul og stedet du oppgir. Før besøket kan vi avklare det praktiske sammen.', 'Dekkskift hjemme hos deg', 'Informasjon om dekkskift hjemme hos deg og hva som kan avklares før avtale.', 'Мобильная замена шин', 'Замена колёс там, где находится автомобиль, по предварительной договорённости.', 'Мы согласуем тип автомобиля, колёса и условия на месте заранее. Так проще понять, какой формат подойдёт в конкретном случае.', 'Мобильная замена шин', 'Спокойная информация о мобильной замене шин и подготовке к визиту.'),
            $this->page('dekkverksted', 20, 'Dekkskift på verksted', 'Dekkskift og annet hjularbeid på verkstedet vårt i Tromsø.', 'På verkstedet kan vi gå gjennom ønsket arbeid og opplysninger om bilen før besøket. Ta gjerne med relevant informasjon om hjul eller dekk.', 'Dekkskift på verksted', 'Informasjon om dekkskift på verksted.', 'Шиномонтаж в мастерской', 'Визит в мастерскую для сезонной замены колёс и работ с шинами.', 'Перед визитом можно уточнить желаемые работы и данные автомобиля. Информация о колёсах или шинах поможет подготовить обращение.', 'Шиномонтаж в мастерской', 'Информация о сезонной замене колёс в мастерской.'),
            $this->page('tesla-og-elbil', 30, 'Tesla og elbil', 'Hjularbeid med oppmerksomhet på bilens løftepunkter.', 'Elbiler kan ha egne hensyn ved løft og håndtering. Oppgi bilmodell når du tar kontakt, så kan behovet vurderes før arbeidet planlegges.', 'Tesla og elbil – hjularbeid', 'Informasjon om hjularbeid for Tesla og andre elbiler.', 'Tesla и электромобили', 'Работы с колёсами с учётом точек подъёма автомобиля.', 'Для электромобилей могут быть важны особенности подъёма и обращения с автомобилем. Сообщите модель при обращении, чтобы обсудить детали заранее.', 'Tesla и электромобили — работы с колёсами', 'Информация о работах с колёсами для Tesla и других электромобилей.'),
            $this->page('bobil-og-varebil', 40, 'Bobil og varebil', 'Dekkskift og hjularbeid for bobil og varebil.', 'Bobil og varebil kan ha andre hjul, dimensjoner og praktiske behov enn personbiler. Del gjerne biltype og hjulopplysninger ved henvendelse.', 'Bobil og varebil – hjularbeid', 'Informasjon om hjularbeid for bobil og varebil.', 'Автодома и фургоны', 'Работы с колёсами для более крупных легковых и коммерческих автомобилей.', 'У автодомов и фургонов могут отличаться колёса, размеры и практические условия. При обращении полезно указать тип автомобиля и сведения о колёсах.', 'Автодома и фургоны — работы с колёсами', 'Информация о работах с колёсами для автодомов и фургонов.'),
            $this->page('dekkhotell', 50, 'Dekkhotell', 'Sesongoppbevaring av hjul når det er avtalt.', 'Dekkhotell kan gjøre sesongskiftet enklere ved å samle oppbevaring og hjuloversikt på ett sted. Spør om innhold og praktiske rammer før du bestemmer deg.', 'Dekkhotell', 'Informasjon om sesongoppbevaring av hjul.', 'Шинный отель', 'Сезонное хранение колёс по договорённости.', 'Шинный отель помогает организовать сезонную смену, когда хранение и сведения о колёсах собраны в одном месте. Перед решением можно уточнить состав услуги и практические условия.', 'Шинный отель', 'Информация о сезонном хранении колёс.'),
            $this->page('dekk-felg-og-hjulpakker', 60, 'Dekk, felg og hjulpakker', 'Vi hjelper deg å finne dekk, felger eller komplette hjul som passer bilen din.', 'Valg av dekk, felg eller hjulpakke avhenger av bilen og behovet ditt. Del registreringsopplysninger eller dimensjonene du kjenner, så kan neste steg avklares.', 'Dekk, felg og hjulpakker', 'Informasjon om dekk, felger og komplette hjulpakker.', 'Шины, диски и колёсные комплекты', 'Помощь в выборе шин, дисков и готовых колёсных комплектов.', 'Выбор шин, дисков или комплекта зависит от автомобиля и ваших задач. Укажите известные данные автомобиля или размеры колёс, чтобы обсудить следующий шаг.', 'Шины, диски и колёсные комплекты', 'Информация о шинах, дисках и готовых колёсных комплектах.'),
        ];
    }

    /** @return array<string, string|int|ServicePagePublicationStatus|ServiceRequestFlow> */
    private function page(string $slug, int $sortOrder, string $titleNb, string $excerptNb, string $bodyNb, string $seoTitleNb, string $seoDescriptionNb, string $titleRu, string $excerptRu, string $bodyRu, string $seoTitleRu, string $seoDescriptionRu): array
    {
        return [
            'slug' => $slug,
            'sort_order' => $sortOrder,
            'publication_status' => ServicePagePublicationStatus::Published,
            'request_flow' => in_array($slug, ['mobilt-dekkskift', 'dekkverksted', 'dekkhotell'], true)
                ? ServiceRequestFlow::Seasonal
                : ServiceRequestFlow::Generic,
            'title_nb' => $titleNb, 'excerpt_nb' => $excerptNb, 'body_nb' => $bodyNb, 'seo_title_nb' => $seoTitleNb, 'seo_description_nb' => $seoDescriptionNb,
            'title_ru' => $titleRu, 'excerpt_ru' => $excerptRu, 'body_ru' => $bodyRu, 'seo_title_ru' => $seoTitleRu, 'seo_description_ru' => $seoDescriptionRu,
        ];
    }
}
