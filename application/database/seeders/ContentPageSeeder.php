<?php

namespace Database\Seeders;

use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Models\ContentPage;
use Illuminate\Database\Seeder;

class ContentPageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $slug => $attributes) {
            $page = ContentPage::query()->firstOrNew(['slug' => $slug]);

            if ($page->exists && $slug === 'kontakt') {
                $page->body_nb = str_replace(
                    'Bruk Google Maps-lenken eller aktiver kartet på siden når du skal besøke verkstedet.',
                    'Bruk Google Maps-lenken eller kartet som vises direkte på siden når du skal besøke verkstedet.',
                    (string) $page->body_nb,
                );
                $page->body_ru = str_replace(
                    'При поездке в мастерскую используйте ссылку Google Maps или активируйте карту на странице.',
                    'При поездке в мастерскую используйте ссылку Google Maps или карту, которая сразу отображается на странице.',
                    (string) $page->body_ru,
                );
                $page->save();
            }

            if ($page->exists && $slug === 'personvern') {
                $page->body_nb = str_replace(
                    'Google Maps lastes ikke inn før du aktivt velger å åpne kartet. Da gjelder Googles egne personvernvilkår.',
                    'Google Maps vises direkte på kontaktsidene og kan da motta IP-adresse og tekniske opplysninger. Googles egne personvernvilkår gjelder for karttjenesten.',
                    (string) $page->body_nb,
                );
                $page->body_ru = str_replace(
                    'Google Maps загружается только после вашего явного действия. После загрузки действуют условия конфиденциальности Google.',
                    'Google Maps отображается непосредственно на страницах контактов и может получать IP-адрес и технические данные. Для карты действуют условия конфиденциальности Google.',
                    (string) $page->body_ru,
                );
                $page->save();
            }

            if ($page->exists && $slug === 'kjopsvilkar' && ! str_contains((string) $page->body_nb, '## Betaling med Dintero')) {
                $page->body_nb = rtrim((string) $page->body_nb).<<<'MD'


## Betaling med Dintero

Ved varebestilling autoriserer Dintero beløpet og reserverer det på kundens betalingsmiddel. Beløpet trekkes ikke ved innsending av bestillingen. Etter bekreftet autorisasjon kontrollerer EasyTire pris og tilgjengelighet på nytt før vareordren sendes til NDI.

EasyTire gjennomfører belastningen først når varene er mottatt hos EasyTire og er kontrollert som klare for avtalt montering eller henting. Dersom NDI avviser ordren, eller pris eller tilgjengelighet ikke kan bekreftes før leverandørbestillingen, frigis reservasjonen uten belastning. Betalingsmiddelets egne vilkår kan begrense hvor lenge en reservasjon varer; hvis leveringstiden overskrider denne perioden, kontakter EasyTire kunden før en ny betalingshandling.
MD;
                $page->body_ru = rtrim((string) $page->body_ru).<<<'MD'


## Оплата через Dintero

При товарном заказе Dintero авторизует сумму и резервирует её на платёжном средстве клиента. При отправке заказа деньги не списываются. После подтверждения резерва EasyTire повторно проверяет цену и наличие до отправки товарного заказа в NDI.

EasyTire выполняет списание только после получения товара в EasyTire и его проверки как готового к согласованной установке или выдаче. Если NDI отклоняет заказ либо цену или наличие нельзя подтвердить до заказа у поставщика, резерв снимается без списания. Срок резерва зависит от выбранного способа оплаты; если доставка длится дольше, EasyTire связывается с клиентом до нового платёжного действия.
MD;
                $page->save();
            }

            if ($page->exists
                && ($page->publication_status !== ContentPageStatus::Draft
                    || mb_strlen(trim((string) $page->body_nb)) >= 200)
            ) {
                continue;
            }

            $page->fill(['slug' => $slug, ...$attributes]);
            $page->save();
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function pages(): array
    {
        return [
            'om-oss' => [
                'sort_order' => 10,
                'publication_status' => ContentPageStatus::Published,
                'title_nb' => 'Om EasyTire',
                'excerpt_nb' => 'Et lokalt bil- og dekkteam i Tromsø med dekkskift hjemme hos deg og eget verksted.',
                'body_nb' => <<<'MD'
EasyTire er den spesialiserte bil- og dekkavdelingen i **EasyTask AS**, org.nr. **934 860 187**.

Vi hjelper privatpersoner og bedrifter med dekkskift, dekkhotell, dekk og felger og praktiske biltjenester i Tromsø. Dekkskift kan utføres hjemme hos deg, på jobb eller på verkstedet når forholdene gjør det mulig.

Målet vårt er enkelt: kort vei fra spørsmål til et tydelig svar, ordentlig arbeid og en pris som er avklart før avtalen inngås.

## Lokalt team og ekte verksted

EasyTire drives av mennesker du kan ringe og møte. Vi bruker egne bilder fra arbeidshverdagen og viser bare tjenester som faktisk kan følges opp av teamet.

## For privatkunder og bedrifter

Vi tilbyr enkeltbestillinger for privatkunder og tilpassede opplegg for bedrifter med flere biler. EasyTire samarbeider blant annet med Avis og følger opp en bilpark på mer enn 300 biler.
MD,
                'meta_title_nb' => 'Om EasyTire | Bil- og dekkteam i Tromsø',
                'meta_description_nb' => 'Bli kjent med EasyTire, et lokalt bil- og dekkteam i Tromsø med dekkskift hjemme hos deg, verksted og bedriftsløsninger.',
                'title_ru' => 'О компании EasyTire',
                'excerpt_ru' => 'Локальная команда автомобильного и шинного сервиса в Тромсё: мобильное обслуживание и собственная мастерская.',
                'body_ru' => <<<'MD'
EasyTire — специализированное направление автомобильного и шинного сервиса компании **EasyTask AS**, org.nr. **934 860 187**.

Мы помогаем частным клиентам и компаниям с сезонной заменой колёс, хранением, покупкой шин и дисков и практическими автомобильными услугами в Тромсё. Работы выполняются в мастерской или с выездом, когда условия и характер задачи это позволяют.

Наша цель проста: быстро ответить на вопрос, качественно выполнить работу и согласовать стоимость до подтверждения заказа.

## Локальная команда и настоящая мастерская

В EasyTire работают люди, которым можно позвонить и которых можно встретить лично. На сайте используются реальные фотографии нашей работы.

## Для частных клиентов и компаний

Мы принимаем разовые заказы и разрабатываем решения для корпоративных автопарков. Среди партнёров EasyTire — Avis, для которой команда обслуживает парк более чем из 300 автомобилей.
MD,
                'meta_title_ru' => 'О компании EasyTire | Автомобильный и шинный сервис в Тромсё',
                'meta_description_ru' => 'EasyTire — локальная команда в Тромсё с мобильным сервисом, мастерской и решениями для компаний.',
            ],
            'kontakt' => [
                'sort_order' => 20,
                'publication_status' => ContentPageStatus::Published,
                'title_nb' => 'Kontakt EasyTire',
                'excerpt_nb' => 'Ring, send e-post eller bruk den nøyaktige kartnålen for å finne riktig innkjøring.',
                'body_nb' => <<<'MD'
**EasyTire / EasyTask AS**

Org.nr. 934 860 187

Ringvegen 200, 9018 Tromsø

Kartnålen viser riktig innkjøring ved Ringvegen 202. Bruk Google Maps-lenken eller kartet som vises direkte på siden når du skal besøke verkstedet.

Besøk og arbeid ved verkstedet skjer etter avtale. En innsendt forespørsel er ikke en bekreftet time før EasyTire har svart.
MD,
                'meta_title_nb' => 'Kontakt EasyTire | Verksted i Tromsø',
                'meta_description_nb' => 'Kontakt EasyTire på telefon eller e-post, og finn riktig innkjøring til verkstedet i Ringvegen i Tromsø.',
                'title_ru' => 'Контакты EasyTire',
                'excerpt_ru' => 'Позвоните, напишите или воспользуйтесь точной меткой, чтобы найти правильный въезд.',
                'body_ru' => <<<'MD'
**EasyTire / EasyTask AS**

Org.nr. 934 860 187

Ringvegen 200, 9018 Tromsø

Метка на карте показывает правильный въезд возле Ringvegen 202. При поездке в мастерскую используйте ссылку Google Maps или карту, которая сразу отображается на странице.

Посещение и выполнение работ происходят по предварительной договорённости. Отправленная заявка не является подтверждённой записью, пока EasyTire не ответит.
MD,
                'meta_title_ru' => 'Контакты EasyTire | Мастерская в Тромсё',
                'meta_description_ru' => 'Телефон, электронная почта и точная отметка мастерской EasyTire в Тромсё.',
            ],
            'personvern' => [
                'sort_order' => 30,
                'publication_status' => ContentPageStatus::Published,
                'title_nb' => 'Personvernerklæring',
                'excerpt_nb' => 'Slik behandler EasyTask AS personopplysninger på EasyTire-nettstedet.',
                'body_nb' => <<<'MD'
Sist oppdatert: 11. august 2026.

## Behandlingsansvarlig

EasyTask AS, org.nr. 934 860 187, Ringvegen 200, 9018 Tromsø, er behandlingsansvarlig. Kontakt: max.easytire@gmail.com eller +47 939 58 270.

## Opplysninger vi behandler

Når du sender en forespørsel eller bestilling, kan vi behandle navn, telefon, e-post, adresse, registreringsnummer, bil- og hjulopplysninger, valgte varer og tjenester, ordrehistorikk, betalingsreferanse og meldingen din. Nettstedet kan også lagre nødvendige sesjonsdata og tekniske sikkerhetslogger.

## Formål og grunnlag

Opplysningene brukes for å svare på forespørsler, inngå og oppfylle avtaler, kontrollere produkt og tilgjengelighet, administrere betaling og ordre, oppfylle lovpålagte plikter og beskytte tjenesten mot misbruk. Grunnlaget er tiltak før avtale, oppfyllelse av avtale, rettslig plikt og berettiget interesse i sikker drift.

## Hvem opplysningene kan deles med

Vi deler bare det som er nødvendig med leverandører som bistår med drift, betaling og ordrebehandling, og med NDI når en vareordre skal kontrolleres eller sendes. Leverandørene skal bare behandle opplysningene for avtalt formål.

Google Maps vises direkte på kontaktsidene og kan da motta IP-adresse og tekniske opplysninger. Googles egne personvernvilkår gjelder for karttjenesten.

## Lagringstid

Opplysninger lagres så lenge de er nødvendige for forespørselen eller avtalen og deretter så lenge lovpålagte dokumentasjons-, regnskaps- og reklamasjonsfrister krever. Sikkerhetslogger slettes eller anonymiseres når de ikke lenger er nødvendige.

## Dine rettigheter

Du kan be om innsyn, retting, sletting, begrensning eller dataportabilitet og protestere mot behandling der vilkårene er oppfylt. Kontakt EasyTire på e-post. Du kan også klage til Datatilsynet.
MD,
                'meta_title_nb' => 'Personvern | EasyTire',
                'meta_description_nb' => 'Les hvordan EasyTask AS behandler personopplysninger på EasyTire-nettstedet.',
                'title_ru' => 'Политика конфиденциальности',
                'excerpt_ru' => 'Как EasyTask AS обрабатывает персональные данные на сайте EasyTire.',
                'body_ru' => <<<'MD'
Последнее обновление: 11 августа 2026 года.

## Ответственный за обработку

EasyTask AS, org.nr. 934 860 187, Ringvegen 200, 9018 Tromsø. Контакты: max.easytire@gmail.com и +47 939 58 270.

## Какие данные мы обрабатываем

При отправке заявки или заказа могут обрабатываться имя, телефон, электронная почта, адрес, регистрационный номер, сведения об автомобиле и колёсах, выбранные товары и услуги, история заказа, платёжная ссылка и текст сообщения. Сайт также использует необходимые данные сессии и технические журналы безопасности.

## Цели и основания

Данные используются для ответа на запросы, заключения и выполнения договора, проверки товара и наличия, обработки оплаты и заказа, выполнения требований закона и защиты сервиса от злоупотреблений.

## Передача данных

Необходимые данные могут передаваться поставщикам хостинга, оплаты и обработки заказов, а также NDI для проверки и размещения заказа. Они могут использовать данные только для согласованных целей.

Google Maps отображается непосредственно на страницах контактов и может получать IP-адрес и технические данные. Для карты действуют условия конфиденциальности Google.

## Срок хранения

Данные хранятся столько, сколько необходимо для заявки или договора, а затем — в пределах обязательных сроков бухгалтерского учёта, документирования и рассмотрения претензий.

## Ваши права

Вы можете запросить доступ, исправление, удаление, ограничение обработки или перенос данных и возразить против обработки, когда это предусмотрено законом. Также можно подать жалобу в Datatilsynet.
MD,
                'meta_title_ru' => 'Конфиденциальность | EasyTire',
                'meta_description_ru' => 'Как EasyTask AS обрабатывает персональные данные пользователей EasyTire.',
            ],
            'kjopsvilkar' => [
                'sort_order' => 40,
                'publication_status' => ContentPageStatus::Published,
                'title_nb' => 'Kjøpsvilkår',
                'excerpt_nb' => 'Vilkår for kjøp av varer og bestilling av tjenester fra EasyTire.',
                'body_nb' => <<<'MD'
Sist oppdatert: 11. august 2026.

## Selger

EasyTask AS (EasyTire), org.nr. 934 860 187, Ringvegen 200, 9018 Tromsø. Telefon: +47 939 58 270. E-post: max.easytire@gmail.com.

## Bestilling og bekreftelse

En tjenesteforespørsel er ikke en bekreftet time. Avtalen inngås først når EasyTire har bekreftet tidspunkt, sted, innhold og pris. Ved varekjøp vises et ordresammendrag før innsending. Dersom pris, lager eller levering endrer seg før ordren kan godkjennes, kontakter vi kunden før bindende gjennomføring.

## Pris og betaling

Prisene vises i norske kroner. Den samlede prisen som godkjennes i bestillingen gjelder. Tjenester med variabelt omfang bekreftes før arbeidet starter. Betalingsmåte og tidspunkt vises i bestillingen eller avtales med EasyTire.

## Betaling med Dintero

Ved varebestilling autoriserer Dintero beløpet og reserverer det på kundens betalingsmiddel. Beløpet trekkes ikke ved innsending av bestillingen. Etter bekreftet autorisasjon kontrollerer EasyTire pris og tilgjengelighet på nytt før vareordren sendes til NDI.

EasyTire gjennomfører belastningen først når varene er mottatt hos EasyTire og er kontrollert som klare for avtalt montering eller henting. Dersom NDI avviser ordren, eller pris eller tilgjengelighet ikke kan bekreftes før leverandørbestillingen, frigis reservasjonen uten belastning. Betalingsmiddelets egne vilkår kan begrense hvor lenge en reservasjon varer; hvis leveringstiden overskrider denne perioden, kontakter EasyTire kunden før en ny betalingshandling.

## Produkter, passform og tilgjengelighet

Kunden må kontrollere bil, registreringsnummer og faktisk hjuldimensjon. Katalogfiltre og kjøretøyoppslag er veiledende. EasyTire kan hjelpe med kontroll før kjøp. Lager og levering kontrolleres mot leverandør før en vareordre sendes endelig.

## Levering og henting

Varebestillinger leveres til EasyTire for henting eller avtalt montering. NDI leverer ikke direkte til sluttkunden under EasyTires nåværende avtale. Forventet tidspunkt bekreftes når leverandørens tilgjengelighet er kontrollert.

## Angrerett

Ved nettkjøp har forbrukeren normalt 14 dagers angrerett etter angrerettloven. Fristen for varer løper normalt fra varen er mottatt. For tjenester kan kunden be om oppstart før fristen er utløpt; ved avbestilling kan kunden måtte betale for den delen som allerede er utført. Angreretten kan falle bort når en tjeneste er fullt utført etter uttrykkelig forhåndssamtykke. Lovens unntak gjelder.

## Reklamasjon og avbestilling

Kontakt EasyTire så raskt som mulig ved feil, skade eller manglende levering. Forbrukerens lovbestemte reklamasjonsrett begrenses ikke av disse vilkårene. Avbestilling av en bekreftet tjeneste må skje så tidlig som mulig; eventuelle kostnader skal være opplyst og avtalt.

## Klager

Klager sendes til max.easytire@gmail.com. Dersom saken ikke løses, kan forbrukeren kontakte Forbrukertilsynet for mekling eller bruke annen tilgjengelig utenrettslig klageordning.
MD,
                'meta_title_nb' => 'Kjøpsvilkår | EasyTire',
                'meta_description_nb' => 'Vilkår for kjøp av dekk og felger og bestilling av EasyTire-tjenester i Tromsø.',
                'title_ru' => 'Условия покупки',
                'excerpt_ru' => 'Условия покупки товаров и заказа услуг EasyTire.',
                'body_ru' => <<<'MD'
Последнее обновление: 11 августа 2026 года.

## Продавец

EasyTask AS (EasyTire), org.nr. 934 860 187, Ringvegen 200, 9018 Tromsø. Телефон: +47 939 58 270. Электронная почта: max.easytire@gmail.com.

## Заказ и подтверждение

Заявка на услугу не является подтверждённой записью. Договор заключается после подтверждения EasyTire времени, места, состава работ и стоимости. Перед отправкой товарного заказа показывается его итог. Если цена, наличие или срок изменились, EasyTire связывается с клиентом до окончательного оформления.

## Цена и оплата

Цены указываются в норвежских кронах. Действует итоговая сумма, подтверждённая в заказе. Стоимость работ с переменным объёмом согласуется до начала. Способ и момент оплаты указываются при заказе или согласуются с EasyTire.

## Оплата через Dintero

При товарном заказе Dintero авторизует сумму и резервирует её на платёжном средстве клиента. При отправке заказа деньги не списываются. После подтверждения резерва EasyTire повторно проверяет цену и наличие до отправки товарного заказа в NDI.

EasyTire выполняет списание только после получения товара в EasyTire и его проверки как готового к согласованной установке или выдаче. Если NDI отклоняет заказ либо цену или наличие нельзя подтвердить до заказа у поставщика, резерв снимается без списания. Срок резерва зависит от выбранного способа оплаты; если доставка длится дольше, EasyTire связывается с клиентом до нового платёжного действия.

## Товары и совместимость

Клиент должен проверить автомобиль, регистрационный номер и фактический размер колёс. Фильтры и подбор по автомобилю носят справочный характер. EasyTire может проверить совместимость до покупки. Наличие и срок поставки проверяются у поставщика.

## Доставка и получение

Товары доставляются в EasyTire для получения или согласованного монтажа. По действующему договору NDI не доставляет товар напрямую конечному покупателю.

## Право отказа

При дистанционной покупке потребитель обычно имеет 14 дней на отказ согласно норвежскому законодательству. Для услуг, начатых по просьбе клиента до окончания этого срока, может удерживаться стоимость уже выполненной части. После полного выполнения услуги с предварительного явного согласия право отказа может прекратиться.

## Претензии и отмена

При ошибке, повреждении или задержке свяжитесь с EasyTire как можно быстрее. Законные права потребителя на предъявление претензии сохраняются. Подтверждённую услугу следует отменять как можно раньше.

## Жалобы

Напишите на max.easytire@gmail.com. Если спор не урегулирован, потребитель может обратиться в Forbrukertilsynet или воспользоваться другой доступной внесудебной процедурой.
MD,
                'meta_title_ru' => 'Условия покупки | EasyTire',
                'meta_description_ru' => 'Условия покупки шин и дисков и заказа услуг EasyTire в Тромсё.',
            ],
        ];
    }
}
