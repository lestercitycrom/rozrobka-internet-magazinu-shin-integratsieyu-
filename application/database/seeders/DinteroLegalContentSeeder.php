<?php

namespace Database\Seeders;

use App\Modules\Content\Models\ContentPage;
use Illuminate\Database\Seeder;

final class DinteroLegalContentSeeder extends Seeder
{
    public function run(): void
    {
        $pages = $this->pages();

        foreach ($pages as $slug => $attributes) {
            ContentPage::query()->where('slug', $slug)->update($attributes);
        }
    }

    /** @return array<string, array<string, string>> */
    private function pages(): array
    {
        return [
            'kjopsvilkar' => [
                'excerpt_nb' => 'Kjøpsvilkår for varer, betaling via Dintero, henting, montering, angrerett og reklamasjon.',
                'body_nb' => <<<'MD'
Sist oppdatert: 24. august 2026. Versjon: 2026-08-24.

## 1. Selger

EasyTask AS, som driver EasyTire, er selger:

- organisasjonsnummer **934 860 187 MVA**;
- Ringvegen 200, 9018 Tromsø, Norge;
- telefon +47 939 58 270;
- e-post max.easytire@gmail.com;
- registrert i Foretaksregisteret og Merverdiavgiftsregisteret.

Avtalen gjelder for forbrukerkjøp i Norge. Bedriftsavtaler inngås separat.

## 2. Varer, tjenester og avtaleinngåelse

Vare, antall, pris, merverdiavgift og eventuelle tjenester vises i ordresammendraget før bestilling. Når du velger **«Bestill med betalingsplikt»**, sender du en bindende bestilling med betalingsplikt dersom varen kan leveres på vilkårene som er vist. EasyTire sender en elektronisk ordrebekreftelse på et varig medium.

En ren tjenesteforespørsel er ikke en bekreftet time. Tid, sted, omfang og pris bekreftes særskilt av EasyTire.

## 3. Pris og avgifter

Alle forbrukerpriser vises i norske kroner og inkluderer 25 % merverdiavgift. Ordresammendraget viser totalpris og merverdiavgift. Det er ingen skjulte eller obligatoriske tillegg som ikke vises før bestilling. Dersom en pris eller obligatorisk kostnad må endres, innhenter EasyTire ny uttrykkelig godkjenning før avtalen endres.

## 4. Betaling med Dintero

Betalingen gjennomføres sikkert av Dintero. Bare betalingsmåtene som til enhver tid vises i Dintero Checkout er tilgjengelige.

Ved varebestilling autoriserer Dintero beløpet og reserverer det på kundens betalingsmiddel. Beløpet trekkes ikke når bestillingen sendes. EasyTire belaster først etter at varene er fysisk mottatt hos EasyTire, kontrollert og klare for avtalt montering eller henting.

Hvis NDI avviser leverandørordren, eller pris eller tilgjengelighet ikke kan bekreftes før leverandørbestilling, frigis reservasjonen uten belastning. Hvis bare deler kan leveres, kontakter EasyTire kunden først. Med kundens godkjenning belastes bare den avtalte delen og resten av reservasjonen frigis.

En reservasjon har begrenset varighet som avhenger av betalingsmåten. Hvis den utløper før varen er klar, belaster EasyTire ikke den gamle reservasjonen. Kunden får i stedet en ny sikker betalingslenke og velger selv om beløpet skal autoriseres på nytt.

## 5. Tilgjengelighet og kompatibilitet

Lager og forventet levering fra NDI kontrolleres før leverandørordren sendes. Opplysninger om lager, leveringstid, kjøretøy og hjuldimensjon er veiledende frem til kontrollen er fullført. Kunden må kontrollere registreringsnummer, faktisk dekkdimensjon, belastningsindeks, hastighetsindeks og øvrige krav til bilen. EasyTire bistår ved tvil.

## 6. Levering, henting og montering

Varene leveres til EasyTire i Tromsø for avtalt henting eller montering; dagens NDI-avtale gir ikke direktelevering til sluttkunden. Forventet tidspunkt opplyses etter leverandørkontroll. Med mindre annet avtales, skjer levering uten unødig opphold og senest 30 dager etter avtaleinngåelsen. Ved forsinkelse gjelder forbrukerkjøpslovens rettigheter.

Risikoen går over når kunden, eller en tredjeperson kunden har utpekt, har overtatt varen. Hvis EasyTire skal montere varen, anses levering normalt skjedd når monteringen er utført.

## 7. Angrerett for varer

Forbrukeren har normalt 14 dagers angrerett. Fristen løper fra dagen kunden eller en utpekt tredjeperson mottar varen. Vanlige lagerførte dekk og felger regnes ikke som spesialtilvirket bare fordi de er bestilt til en bestemt bil.

Gi en entydig melding til EasyTire før fristen, for eksempel på e-post. Du kan bruke [standard angreskjema](/dokumenter/angreskjema-easytire.pdf), men det er ikke obligatorisk. Varen må returneres uten unødig opphold og senest 14 dager etter meldingen.

Direkte returkostnader dekkes av EasyTire. Kontakt EasyTire før retur slik at transport eller levering kan avtales. Kunden kan bli ansvarlig for verdireduksjon som skyldes annen håndtering enn det som er nødvendig for å fastslå varens art, egenskaper og funksjon. Tilbakebetaling skjer etter angrerettloven og med samme betalingsmiddel med mindre annet avtales.

## 8. Tjenester før angrefristen

Hvis en tjeneste skal starte før 14-dagersfristen er utløpt, ber EasyTire om en separat uttrykkelig anmodning. Ved senere bruk av angreretten kan kunden måtte betale et rimelig beløp for den delen som allerede er utført. Angreretten for en fullt utført tjeneste faller bare bort når tjenesten er fullført etter uttrykkelig forhåndssamtykke og kunden har erkjent at angreretten da går tapt.

## 9. Avbestilling og manglende oppmøte

Varer kan avbestilles før levering. EasyTire kan ikke tvinge gjennom kjøpet, men kan kreve dokumentert, rimelig tap som loven tillater. Det brukes ingen automatisk eller vilkårlig standardbot. En bekreftet tjenestetime bør avbestilles så tidlig som mulig; eventuelt krav må bygge på en tydelig avtale og et dokumentert rimelig tap.

## 10. Reklamasjon

Kontakt EasyTire innen rimelig tid etter at en mangel ble eller burde blitt oppdaget. Forbrukerens lovbestemte reklamasjonsrett etter forbrukerkjøpsloven begrenses ikke av vilkårene. EasyTire kan be om bilder og mulighet til å undersøke varen, og følger lovens regler om retting, omlevering, prisavslag, heving og erstatning.

## 11. Kansellering, frigivelse og refusjon

En ikke belastet reservasjon frigis når ordren ikke skal gjennomføres. Et belastet beløp refunderes helt eller delvis når det følger av avtalen eller loven. EasyTire registrerer operasjonen mot den opprinnelige Dintero-transaksjonen og sender oppdatert bekreftelse.

## 12. Personvern og klager

Se [personvernerklæringen](/personvern) for behandling av personopplysninger. Klager sendes til max.easytire@gmail.com. Hvis saken ikke løses, kan forbrukeren kontakte Forbrukertilsynet for mekling. Partene kan også bringe saken inn for Forbrukerklageutvalget eller ordinære domstoler.
MD,
                'meta_title_nb' => 'Kjøpsvilkår og Dintero-betaling | EasyTire',
                'meta_description_nb' => 'Vilkår for dekk, felger, betaling via Dintero, henting, montering, angrerett og reklamasjon hos EasyTire i Tromsø.',
                'excerpt_ru' => 'Условия покупки товаров, оплаты через Dintero, получения, установки, отказа и рекламаций.',
                'body_ru' => <<<'MD'
Последнее обновление: 24 августа 2026 года. Версия: 2026-08-24.

## 1. Продавец

Продавец — EasyTask AS, работающая под брендом EasyTire:

- организация **934 860 187 MVA**;
- Ringvegen 200, 9018 Tromsø, Норвегия;
- телефон +47 939 58 270;
- e-mail max.easytire@gmail.com;
- зарегистрирована в Foretaksregisteret и Merverdiavgiftsregisteret.

Условия относятся к покупкам потребителей в Норвегии. Корпоративные договоры заключаются отдельно.

## 2. Заказ и договор

До отправки заказа показываются товар, количество, цена, НДС и выбранные услуги. Нажимая **«Заказать с обязательством оплаты»**, покупатель отправляет обязательный заказ с обязанностью оплаты, если товар может быть поставлен на показанных условиях. EasyTire отправляет электронное подтверждение на долговечном носителе.

Заявка только на услугу не является подтверждённой записью: время, место, объём и цена согласуются отдельно.

## 3. Цена и налоги

Цены для потребителя указаны в NOK и включают 25 % НДС. Итог и НДС видны до заказа. Скрытых обязательных доплат нет. Изменение цены или обязательных расходов возможно только после нового явного подтверждения клиента.

## 4. Оплата Dintero

Платёж безопасно обрабатывает Dintero. Доступны только способы, которые показаны в Dintero Checkout.

Dintero сначала авторизует сумму и резервирует её. При отправке заказа деньги не списываются. EasyTire списывает сумму только после физического получения товара, проверки и готовности к выдаче или согласованной установке.

Если NDI отклонит заказ либо цена или наличие не подтвердятся, резерв снимается без списания. При частичной поставке EasyTire сначала связывается с клиентом; после согласия списывается только согласованная часть, а остаток резерва освобождается.

Срок резерва зависит от способа оплаты. После истечения старый резерв не списывается: клиент получает новую безопасную ссылку и сам решает, авторизовать ли сумму повторно.

## 5. Наличие и совместимость

Наличие и срок NDI проверяются до заказа поставщику. Данные о складе, автомобиле и размере колёс носят справочный характер до проверки. Клиент подтверждает регистрационный номер, фактический размер, индексы нагрузки и скорости и другие требования автомобиля. EasyTire помогает при сомнениях.

## 6. Получение и установка

Товар поступает в EasyTire в Тромсё для выдачи или установки; текущий договор NDI не предусматривает прямую доставку покупателю. Если не согласовано иное, поставка выполняется без неоправданной задержки и не позднее 30 дней. Риск переходит после получения товара, а при установке EasyTire — обычно после завершения установки.

## 7. Право отказа

Потребитель обычно может отказаться в течение 14 дней с получения товара. Обычные складские шины и диски не становятся индивидуальным товаром только потому, что выбраны для конкретного автомобиля.

До истечения срока отправьте EasyTire однозначное уведомление. Можно использовать [стандартную форму отказа](/dokumenter/angreskjema-easytire.pdf). Вернуть товар нужно не позднее 14 дней после уведомления.

Прямые расходы на возврат оплачивает EasyTire. Сначала свяжитесь с EasyTire для согласования транспорта или передачи. Покупатель отвечает только за снижение стоимости из-за обращения сверх необходимого для проверки товара. Возврат денег выполняется по закону тем же способом оплаты, если не согласовано иное.

## 8. Услуги до окончания 14 дней

Для начала услуги до окончания срока EasyTire получает отдельную явную просьбу. При отказе может оплачиваться разумная стоимость уже выполненной части. Право отказа от полностью выполненной услуги прекращается только после предварительного явного согласия и подтверждения понимания этого последствия.

## 9. Отмена и неявка

Товар можно отменить до поставки. EasyTire не принуждает к покупке, но вправе требовать разрешённый законом документированный разумный ущерб. Автоматического произвольного штрафа нет. Подтверждённую услугу следует отменять как можно раньше; требование возможно только при ясном соглашении и документированном разумном ущербе.

## 10. Рекламации

Свяжитесь с EasyTire в разумный срок после обнаружения недостатка. Законные права по forbrukerkjøpsloven не ограничиваются этими условиями. EasyTire следует правилам ремонта, замены, снижения цены, расторжения и возмещения.

## 11. Снятие резерва и возврат

Не списанный резерв освобождается, если заказ не выполняется. Списанная сумма возвращается полностью или частично, когда это предусмотрено договором или законом. EasyTire проводит операцию по исходной транзакции Dintero и отправляет обновлённое подтверждение.

## 12. Конфиденциальность и жалобы

См. [политику конфиденциальности](/ru/personvern). Жалобы: max.easytire@gmail.com. Если договориться не удалось, потребитель может обратиться в Forbrukertilsynet, Forbrukerklageutvalget или суд.
MD,
                'meta_title_ru' => 'Условия покупки и оплаты Dintero | EasyTire',
                'meta_description_ru' => 'Условия покупки шин и дисков, оплаты Dintero, получения, установки, отказа и рекламаций EasyTire.',
            ],
            'personvern' => [
                'excerpt_nb' => 'Hvordan EasyTask AS behandler personopplysninger i EasyTire-butikken og Dintero-betalingen.',
                'body_nb' => <<<'MD'
Sist oppdatert: 24. august 2026. Versjon: 2026-08-24.

## Behandlingsansvarlig

EasyTask AS, org.nr. 934 860 187 MVA, Ringvegen 200, 9018 Tromsø, er behandlingsansvarlig for EasyTire-butikken. Kontakt: max.easytire@gmail.com eller +47 939 58 270.

## Opplysninger, formål og behandlingsgrunnlag

Ved forespørsel og kjøp behandler vi navn, telefon, e-post, registreringsnummer, bil- og hjulopplysninger, valgte varer og tjenester, ordrehistorikk, samtykke-/vilkårsversjon, leverandørstatus og betalingsreferanser. Nødvendige sesjonsdata, IP-adresse og sikkerhetslogger brukes for sikker drift og misbruksvern.

Behandlingen er nødvendig for tiltak før avtale og oppfyllelse av avtale, for regnskaps- og dokumentasjonsplikter og for EasyTires berettigede interesse i sikker drift, kundeservice og dokumentasjon av transaksjoner. Markedsføring basert på samtykke er ikke en forutsetning for kjøp.

## Mottakere

Nødvendige opplysninger deles med:

- **Dintero**, som leverer Checkout og behandler identitets-, kjøps-, enhets- og betalingsopplysninger etter sitt eget og avtalte behandlingsansvar;
- **NDI Norge AS**, for lagerkontroll og leverandørordre;
- leverandører av hosting, e-post, kø/logg og teknisk drift som databehandlere;
- Google når kartet lastes på kontaktsiden; Google kan da motta IP-adresse og tekniske opplysninger.

Vi selger ikke personopplysninger. Overføring utenfor EØS skjer bare når en leverandør bruker et gyldig overføringsgrunnlag og nødvendige sikkerhetstiltak.

## Lagringstid

- Avbrutte handlekurver og nødvendige sesjonsdata slettes automatisk eller utløper normalt innen 30 dager.
- Forespørsler uten avtale slettes eller anonymiseres normalt innen 12 måneder etter siste kontakt, med mindre videre oppbevaring er nødvendig for en konkret tvist.
- Ordre-, betalings- og regnskapsdokumentasjon oppbevares i **fem år etter utløpet av regnskapsåret**, eller lenger når lov, reklamasjon eller en pågående tvist krever det.
- Sikkerhetslogger oppbevares normalt inntil 90 dager, med lengre sperret oppbevaring bare ved en konkret sikkerhetshendelse.

## Dine rettigheter

Du kan be om innsyn, retting, sletting, begrensning og dataportabilitet, og protestere mot behandling basert på berettiget interesse. Et samtykke kan trekkes tilbake uten å påvirke tidligere lovlig behandling. Identitet kan måtte kontrolleres før opplysninger utleveres. Du kan klage til **Datatilsynet** på datatilsynet.no.

## Informasjonssikkerhet og endringer

Tilgang begrenses etter tjenstlig behov, hemmeligheter lagres utenfor kildekode, og betaling bekreftes server-til-server. Vesentlige endringer får ny versjonsdato. Versjonen som ble akseptert ved bestilling lagres sammen med ordren.
MD,
                'meta_title_nb' => 'Personvern og Dintero | EasyTire',
                'meta_description_nb' => 'Les hvordan EasyTask AS behandler ordre-, kjøretøy- og betalingsopplysninger i EasyTire og Dintero Checkout.',
                'excerpt_ru' => 'Как EasyTask AS обрабатывает данные в магазине EasyTire и при оплате Dintero.',
                'body_ru' => <<<'MD'
Последнее обновление: 24 августа 2026 года. Версия: 2026-08-24.

## Ответственный

EasyTask AS, org.nr. 934 860 187 MVA, Ringvegen 200, 9018 Tromsø, отвечает за данные магазина EasyTire. Контакты: max.easytire@gmail.com и +47 939 58 270.

## Данные, цели и основания

При заявке и заказе обрабатываются имя, телефон, e-mail, регистрационный номер, данные автомобиля и колёс, товары и услуги, история заказа, версия принятых документов, статус поставщика и платёжные ссылки. Для безопасности используются необходимые данные сессии, IP-адрес и технические журналы.

Основания: действия до заключения договора, исполнение договора, бухгалтерские обязанности и законный интерес в безопасной работе, поддержке клиента и подтверждении операций. Маркетинговое согласие не требуется для покупки.

## Получатели

Необходимые данные получают Dintero для Checkout и оплаты, NDI Norge AS для проверки и заказа, а также поставщики хостинга, e-mail и технической эксплуатации. При загрузке Google Maps Google получает IP-адрес и технические данные. Данные не продаются. Передача за пределы ЕЭЗ допускается только при законном основании и мерах защиты.

## Сроки хранения

- сессии и брошенные корзины обычно удаляются или истекают не позднее 30 дней;
- заявки без договора обычно удаляются или обезличиваются через 12 месяцев после последнего контакта;
- заказы, платежи и бухгалтерские документы хранятся **пять лет после окончания отчётного года**, а при рекламации или споре — дольше в необходимом объёме;
- журналы безопасности обычно хранятся до 90 дней.

## Права

Можно запросить доступ, исправление, удаление, ограничение, переносимость данных и возразить против обработки по законному интересу. Согласие можно отозвать. Для выдачи данных может потребоваться подтверждение личности. Жалобу можно подать в **Datatilsynet**: datatilsynet.no.

## Безопасность и изменения

Доступ ограничивается рабочей необходимостью, секреты хранятся вне кода, а платёж подтверждается сервером. Существенные изменения получают новую дату версии; принятая при заказе версия сохраняется с заказом.
MD,
                'meta_title_ru' => 'Конфиденциальность и Dintero | EasyTire',
                'meta_description_ru' => 'Обработка заказов, данных автомобиля и оплаты EasyTask AS в EasyTire и Dintero Checkout.',
            ],
        ];
    }
}
