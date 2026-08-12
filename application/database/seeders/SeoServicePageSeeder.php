<?php

namespace Database\Seeders;

use App\Modules\Content\CustomerSeoWorkbook;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Enums\ServiceRequestFlow;
use App\Modules\Content\Models\ServicePage;
use Illuminate\Database\Seeder;

/**
 * Imports the customer-approved SEO copy once. A page that already has a
 * canonical path is considered editor-owned and is never overwritten.
 */
final class SeoServicePageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $page) {
            $existing = ServicePage::query()->where('slug', $page['slug'])->first();

            if ($existing === null) {
                ServicePage::query()->create($page);

                continue;
            }

            if ($existing->canonical_path === null) {
                $existing->forceFill($page)->save();
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function pages(): array
    {
        $pages = [
            $this->page(
                slug: 'mobilt-dekkskift',
                path: 'dekkskift-tromso',
                order: 10,
                flow: ServiceRequestFlow::Seasonal,
                titleNb: 'Dekkskift i Tromsø',
                excerptNb: 'Bestill dekkskift der bilen står – hjemme eller på arbeidsplassen – eller kom til vårt verksted i Tromsø. Vi gjør hjulskiftet raskt og profesjonelt på tidspunktet som passer deg.',
                bodyNb: <<<'MD'
## Dekkskift der bilen står

Slipp å kjøre til verksted og vente på tur. EasyTire kommer til deg og skifter hjulene der bilen allerede står – hjemme, på arbeidsplassen eller et annet egnet sted.

Alt vi trenger er trygg og tilstrekkelig plass rundt bilen. Du velger adresse og ønsket tidspunkt i forespørselen, og vi bekrefter avtalen før oppmøte.

## Dekkskift på verksted i Tromsø

Foretrekker du å komme til oss, kan du be om dekkskift på vårt verksted i Tromsø. Velg ønsket dato og ta med hjulene som skal monteres. Tidspunktet er ikke bekreftet før du har fått svar fra oss.

## Slik fungerer dekkskift med EasyTire

1. Velg om bilen står hjemme, på jobb eller om du kommer til verkstedet.
2. Oppgi bilen, hjulene og ønsket dato.
3. Vi kontakter deg og bekrefter tidspunkt og praktiske detaljer.
4. Vi utfører hjulskiftet på avtalt sted.

## Når skal man skifte til vinterdekk og sommerdekk i Tromsø?

I Nordland, Troms og Finnmark kan piggdekk brukes fra og med 16. oktober til og med 30. april. Dersom føret krever det, er piggdekk også tillatt utenom disse datoene. Piggfrie vinterdekk kan brukes hele året. Som sjåfør er du alltid ansvarlig for at bilen har tilstrekkelig veigrep.

[Les gjeldende dekkregler hos Statens vegvesen](https://www.vegvesen.no/kjoretoy/eie-og-vedlikeholde/dekk-og-kjetting/).

## Ofte stilte spørsmål om dekkskift

### Hvor lang tid tar et dekkskift?

Et vanlig dekkskift tar i gjennomsnitt omtrent 20 minutter. Tiden kan variere etter biltype, hjul og forholdene på stedet.

### Kan dere skifte hjul på parkeringsplassen eller arbeidsplassen min?

Ja, dersom bilen står på fast og forholdsvis jevnt underlag og det er omtrent 1–1,5 meter ledig plass rundt bilen.

### Kan dere skifte hjul på Tesla og andre elbiler?

Ja. Vi bruker egnet utstyr og riktige løftepunkter for den aktuelle bilen.

### Må jeg være til stede?

Nei, men vi trenger avtalt tilgang til bilen og hjulene som skal monteres.
MD,
                seoTitleNb: 'Dekkskift i Tromsø – hjemme, på jobb eller verksted | EasyTire',
                seoDescriptionNb: 'Bestill dekkskift der bilen står – hjemme eller på arbeidsplassen – eller kom til vårt verksted i Tromsø. Vi gjør hjulskiftet raskt og profesjonelt på tidspunktet som passer deg.',
                titleRu: 'Замена колёс в Тромсё',
                excerptRu: 'Закажите замену колёс у дома, на работе или в мастерской EasyTire в Тромсё. После заявки мы подтвердим время и практические детали.',
                bodyRu: <<<'MD'
## Замена колёс там, где находится автомобиль

EasyTire может приехать к дому, на работу или в другое подходящее место. Автомобиль должен стоять на твёрдой ровной поверхности, а вокруг него должно быть достаточно свободного пространства.

## Замена колёс в мастерской

Можно приехать в нашу мастерскую в Тромсё. Укажите желаемую дату в заявке; время считается подтверждённым только после нашего ответа.

## Как это работает

1. Выберите место обслуживания.
2. Укажите автомобиль, колёса и желаемую дату.
3. Мы свяжемся с вами и подтвердим детали.
4. Выполним работу в согласованное время.

## Частые вопросы

### Сколько времени занимает замена?

Обычно около 20 минут, но время зависит от автомобиля, колёс и условий на месте.

### Вы работаете с Tesla и другими электромобилями?

Да, мы используем подходящее оборудование и штатные точки подъёма автомобиля.
MD,
                seoTitleRu: 'Замена колёс в Тромсё | EasyTire',
                seoDescriptionRu: 'Замена колёс у дома, на работе или в мастерской EasyTire в Тромсё.',
            ),
            $this->page(
                slug: 'dekkhotell', path: 'dekkhotell-tromso', order: 50, flow: ServiceRequestFlow::Seasonal,
                titleNb: 'Dekkhotell i Tromsø',
                excerptNb: 'Slipp å lagre og transportere hjulene selv. Vi vasker, kontrollerer og oppbevarer hjulsettet trygt frem til neste sesong.',
                bodyNb: <<<'MD'
## Dette er inkludert i EasyTire dekkhotell

Med EasyTire dekkhotell får du trygg oppbevaring av ett komplett hjulsett i opptil seks måneder.

- vask av hjulene før lagring
- kontroll og justering av lufttrykk
- kontroll av mønsterdybde og synlig dekkslitasje
- registrering og organisert oppbevaring av hjulsettet
- beskjed dersom dekkene bør repareres eller skiftes før neste sesong

## Riktig oppbevaring av hjul

Hjulene oppbevares organisert og beskyttet mot vær, snø, fuktighet og direkte sollys. Før lagring vaskes de slik at salt, skitt og veistøv ikke blir liggende på dekk og felger gjennom sesongen.

## Slik fungerer dekkhotell når vi kommer til deg

1. Send forespørsel om dekkskift og dekkhotell.
2. Vi avtaler hjul og praktiske detaljer.
3. Vi skifter hjulene hjemme eller på jobb.
4. Vi tar med, vasker og kontrollerer det andre hjulsettet.
5. Hjulene lagres frem til neste sesong.

## Ofte stilte spørsmål om dekkhotell

### Hvor lenge oppbevares hjulene?

Ett hjulsett oppbevares i opptil seks måneder, som tilsvarer en sesong.

### Er dekkskift inkludert i prisen?

Nei. Dekkhotell og dekkskift er separate tjenester med separate priser.

### Må jeg transportere hjulene selv?

Ikke dersom du bestiller dekkskift hjemme eller på jobb og transporten er avtalt med oss.

### Kan jeg hente hjulene under lagringsperioden?

Ja. Gi oss beskjed minst en til to dager på forhånd, så avtaler vi et tidspunkt.
MD,
                seoTitleNb: 'Dekkhotell i Tromsø – trygg lagring av dekk | EasyTire',
                seoDescriptionNb: 'Dekkhotell i Tromsø med dekkskift hjemme, på jobb eller på verksted. Trygg oppbevaring av hjul mellom sesongene.',
                titleRu: 'Шинный отель в Тромсё', excerptRu: 'Хранение одного комплекта колёс до шести месяцев: мойка, контроль и организованное хранение до следующего сезона.',
                bodyRu: <<<'MD'
## Что входит в услугу

- хранение одного комплекта колёс до шести месяцев
- мойка перед хранением
- проверка давления, глубины протектора и видимого износа
- регистрация и организованное хранение комплекта

## Как это работает

Мы согласуем замену и перевозку колёс, моем и проверяем снятый комплект, а затем храним его до следующего сезона.

## Частые вопросы

### Включена ли замена колёс?

Нет. Замена колёс и шинный отель оплачиваются как отдельные услуги.

### Можно ли забрать колёса раньше?

Да. Сообщите нам за один-два дня, чтобы согласовать время.
MD,
                seoTitleRu: 'Шинный отель в Тромсё | EasyTire', seoDescriptionRu: 'Мойка, контроль и сезонное хранение колёс в Тромсё.',
            ),
            $this->page(
                slug: 'tesla-og-elbil', path: 'dekkskift-tesla-elbil-tromso', order: 30, flow: ServiceRequestFlow::Seasonal,
                titleNb: 'Dekkskift for Tesla og elbil i Tromsø',
                excerptNb: 'Vi utfører dekkskift på Tesla og andre elbiler hjemme, på jobb og på verksted, med egnet løfteutstyr og riktige løftepunkter.',
                bodyNb: <<<'MD'
## Dekkskift for Tesla og andre elbiler

Vi har erfaring med ulike elbilmerker og vet at elbiler kan kreve ekstra oppmerksomhet på grunn av vekt, løftepunkter og hjulstørrelse. Arbeidet kan utføres hjemme, på arbeidsplassen eller på verkstedet.

## Riktig løfteutstyr og korrekte løftepunkter

Batteriet på en elbil er normalt plassert i gulvet. Vi følger bilprodusentens angitte løftepunkter og bruker egnet utstyr og nødvendige adaptere. Hjulbolter eller hjulmuttere strammes med riktig moment.

## Ofte stilte spørsmål om dekkskift for elbil

### Bruker dere spesielle løfteadaptere?

Vi bruker adaptere som er egnet for bilen når det er nødvendig.

### Kan dere skifte hjul hjemme eller på arbeidsplassen?

Ja, dersom bilen står på et fast og jevnt underlag med nok plass rundt seg.

### Kan hjulene oppbevares på dekkhotellet?

Ja. Dekkskiftet kan kombineres med EasyTire dekkhotell etter avtale.
MD,
                seoTitleNb: 'Dekkskift for Tesla og elbil i Tromsø | EasyTire', seoDescriptionNb: 'Bestill dekkskift for Tesla og andre elbiler i Tromsø. Egnet løfteutstyr og riktige løftepunkter hjemme, på jobb eller på verksted.',
                titleRu: 'Замена колёс Tesla и электромобилей в Тромсё', excerptRu: 'Работаем с Tesla и другими электромобилями, соблюдая штатные точки подъёма и требования конкретной модели.',
                bodyRu: <<<'MD'
## Tesla и другие электромобили

Электромобили требуют особого внимания к весу, точкам подъёма и размеру колёс. Мы работаем дома, на работе клиента или в мастерской.

## Правильное подъёмное оборудование

Мы используем подходящее оборудование и адаптеры, следуем указаниям производителя и затягиваем крепёж с требуемым моментом.

## Частые вопросы

### Можно ли выполнить работу на парковке?

Да, если поверхность твёрдая и ровная, а вокруг автомобиля достаточно места.
MD,
                seoTitleRu: 'Замена колёс Tesla в Тромсё | EasyTire', seoDescriptionRu: 'Замена колёс Tesla и других электромобилей в Тромсё.',
            ),
            $this->page(
                slug: 'omlegging-balansering', path: 'omlegging-balansering-dekk-tromso', order: 70, flow: ServiceRequestFlow::Generic,
                titleNb: 'Omlegging og balansering av dekk i Tromsø', excerptNb: 'Vi monterer dekk på felg, balanserer hjul og kontrollerer at hjulene er klare for trygg kjøring.',
                bodyNb: <<<'MD'
## Omlegging og balansering – eller kun balansering

Omlegging passer når nye dekk skal monteres på eksisterende felger. Kun balansering passer når dekkene allerede er montert, men du opplever vibrasjoner, ujevn kjøring eller har mistet en balansevekt.

## Kjøp dekk hos EasyTire

Vi selger sommerdekk, vinterdekk og helårsdekk. Oppgi registreringsnummer eller kjent dimensjon, så kan vi hjelpe deg å finne produkter som passer bilen.

## Ofte stilte spørsmål

### Kan jeg bestille bare balansering?

Ja. Du kan be om balansering av ett eller flere hjul uten omlegging.

### Kan jeg ta med egne dekk?

Ja, så lenge dekkene passer felgene og bilen.

### Er ønsket dato automatisk bekreftet?

Nei. Datoen er et ønske; vi kontakter deg for å bekrefte dato og tidspunkt.
MD,
                seoTitleNb: 'Omlegging og balansering av dekk i Tromsø | EasyTire', seoDescriptionNb: 'Profesjonell omlegging og balansering av dekk i Tromsø. Montering på felg, balansering og kontroll.',
                titleRu: 'Монтаж и балансировка шин в Тромсё', excerptRu: 'Монтажируем шины на диски, балансируем колёса и проверяем их перед эксплуатацией.',
                bodyRu: "## Монтаж и балансировка\n\nМожно заказать полный шиномонтаж или только балансировку одного или нескольких колёс.\n\n## Важно о дате\n\nВыбранная дата является пожеланием. Мы свяжемся с вами и отдельно подтвердим дату и время.",
                seoTitleRu: 'Монтаж и балансировка шин в Тромсё | EasyTire', seoDescriptionRu: 'Монтаж и балансировка автомобильных шин в Тромсё.',
            ),
            $this->page(
                slug: 'dekkreparasjon', path: 'dekkreparasjon-tromso', order: 80, flow: ServiceRequestFlow::Generic,
                titleNb: 'Reparasjon av punktert dekk i Tromsø', excerptNb: 'Vi kontrollerer skaden og reparerer dekket dersom det kan gjøres på en trygg og forsvarlig måte.',
                bodyNb: <<<'MD'
## Hvilke dekkskader kan repareres?

Mindre punkteringer i dekkets slitebane kan ofte repareres. Skader i sideveggen, store hull, sprekker eller skader etter kjøring med svært lavt lufttrykk kan normalt ikke repareres. Dekket vurderes alltid før arbeidet starter.

## Slik utfører vi dekkreparasjon

1. Vi finner lekkasjen og kontrollerer skaden.
2. Dekket demonteres fra felgen.
3. Skaden vurderes og repareres fra innsiden når dette er forsvarlig.
4. Dekket monteres og fylles med riktig lufttrykk.
5. Hjulet kontrolleres for lekkasje og balanseres ved behov.

## Ofte stilte spørsmål om punktert dekk

### Kan jeg kjøre med et punktert dekk?

Du bør ikke fortsette dersom dekket har mistet mye luft. Kjøring med for lavt lufttrykk kan skade både dekket og felgen.

### Kan et dekk med spiker eller skrue repareres?

Ofte, dersom hullet er lite og ligger i slitebanen. Dekket må undersøkes før vi kan avgjøre om reparasjon er forsvarlig.

### Kan sideveggen repareres?

Nei, skader i sideveggen kan normalt ikke repareres på en sikker måte.
MD,
                seoTitleNb: 'Reparasjon av punktert dekk i Tromsø | EasyTire', seoDescriptionNb: 'Har du punktert dekk? EasyTire kontrollerer og reparerer bildekk i Tromsø når skaden kan utbedres forsvarlig.',
                titleRu: 'Ремонт проколотой шины в Тромсё', excerptRu: 'Проверим повреждение и отремонтируем шину, если это можно сделать безопасно.',
                bodyRu: "## Какие повреждения можно ремонтировать?\n\nНебольшие проколы в зоне протектора часто подлежат ремонту. Повреждения боковины, крупные отверстия и последствия езды без давления обычно требуют замены шины.\n\n## Как проходит ремонт\n\nМы находим утечку, снимаем шину, оцениваем и ремонтируем её изнутри, затем проверяем герметичность.",
                seoTitleRu: 'Ремонт шин в Тромсё | EasyTire', seoDescriptionRu: 'Проверка и безопасный ремонт проколотых шин в Тромсё.',
            ),
            $this->page(
                slug: 'tpms-sensor', path: 'tpms-sensor-tromso', order: 90, flow: ServiceRequestFlow::Generic,
                titleNb: 'TPMS-sensor og dekktrykksensor i Tromsø', excerptNb: 'Lyser dekktrykkslampen? Vi hjelper med kontroll, service og bytte av TPMS-sensorer.',
                bodyNb: <<<'MD'
## Dekktrykkslampen lyser – hva kan være årsaken?

Varslingen kan skyldes for lavt lufttrykk, feil på en TPMS-sensor eller at sensoren ikke kommuniserer riktig med bilen. Vi kontrollerer dekktrykket og systemet for å finne årsaken.

## Bytte og service av TPMS-sensorer

Vi kan kontrollere og bytte defekte sensorer og hjelpe med programmering når det er nødvendig. Oppgi registreringsnummeret og beskriv problemet i forespørselen.

## Ofte stilte spørsmål om TPMS

### Hvorfor lyser lampen selv om lufttrykket er riktig?

Det kan skyldes en defekt sensor, tomt sensorbatteri eller behov for programmering.

### Kan sensoren byttes hjemme?

Nei. Dekket må demonteres fra felgen, så selve byttet utføres på verkstedet.
MD,
                seoTitleNb: 'TPMS-sensor i Tromsø – bytte og service | EasyTire', seoDescriptionNb: 'Lyser dekktrykkslampen? Kontroll, service og bytte av TPMS-sensorer i Tromsø.',
                titleRu: 'TPMS и датчики давления в Тромсё', excerptRu: 'Проверка, обслуживание и замена датчиков давления в шинах.',
                bodyRu: "## Почему горит индикатор давления?\n\nПричиной может быть низкое давление, неисправный датчик или потеря связи с автомобилем.\n\n## Замена датчика\n\nДля замены шину нужно снять с диска, поэтому работа выполняется в мастерской.",
                seoTitleRu: 'TPMS в Тромсё | EasyTire', seoDescriptionRu: 'Проверка и замена датчиков давления в шинах в Тромсё.',
            ),
            $this->page(
                slug: 'oljeskift', path: 'oljeskift-tromso', order: 100, flow: ServiceRequestFlow::Generic,
                titleNb: 'Oljeskift i Tromsø', excerptNb: 'Vi utfører oljeskift hjemme hos deg eller på verkstedet. Tjenesten inkluderer motorolje og oljefilter tilpasset bilen.',
                bodyNb: <<<'MD'
## Hva er inkludert i oljeskiftet?

Oljeskiftet inkluderer motorolje som oppfyller bilprodusentens spesifikasjoner og nytt oljefilter tilpasset bilen. Luftfilter og kupefilter kan avtales som tillegg.

## Hvor ofte bør motoroljen skiftes?

Intervallet varierer etter bilmodell, motortype, kjøremønster og produsentens anbefalinger. Følg bilens serviceplan; mye småkjøring, kaldstarter og krevende forhold kan gi behov for hyppigere skift.

## Oljeskift hjemme – når forholdene er egnet

Bilen må stå på fast, plant og rent underlag med god tilgang. Ved regn, snø eller sterk vind anbefaler vi garasje, egnet tak eller verkstedet. Dersom forholdene ikke er forsvarlige, avtaler vi en annen løsning.

## Ofte stilte spørsmål om oljeskift

### Kan luftfilter og kupefilter skiftes samtidig?

Ja, begge kan avtales som tilleggstjenester.

### Hva skjer med den gamle oljen?

Brukt olje og filter tas med og håndteres på en forsvarlig måte.
MD,
                seoTitleNb: 'Oljeskift i Tromsø – hjemme eller på verksted | EasyTire', seoDescriptionNb: 'Bestill oljeskift i Tromsø hjemme eller på verksted. Motorolje og oljefilter, med mulige tillegg.',
                titleRu: 'Замена масла в Тромсё', excerptRu: 'Замена моторного масла и масляного фильтра дома у клиента или в мастерской.',
                bodyRu: "## Что входит\n\nМоторное масло требуемой спецификации и новый масляный фильтр. Воздушный и салонный фильтры можно согласовать дополнительно.\n\n## Работа на выезде\n\nАвтомобиль должен стоять на твёрдой, ровной и чистой поверхности. При небезопасных погодных условиях мы предложим мастерскую или другое время.",
                seoTitleRu: 'Замена масла в Тромсё | EasyTire', seoDescriptionRu: 'Замена масла дома или в мастерской в Тромсё.',
            ),
            $this->page(
                slug: 'bilbatteri', path: 'bilbatteri-tromso', order: 110, flow: ServiceRequestFlow::Generic,
                titleNb: 'Bilbatteri i Tromsø', excerptNb: 'Vi tilbyr batteritest, starthjelp og bytte av bilbatteri hjemme hos deg eller på verkstedet.',
                bodyNb: <<<'MD'
## Batteritest, starthjelp og bytte av bilbatteri

Vi hjelper når bilen ikke starter, batteriet lades raskt ut eller du ønsker å kontrollere batteriets tilstand. Beskriv problemet og oppgi registreringsnummeret, så vurderer vi om bilen trenger test, starthjelp eller et nytt batteri.

## Hjelp hjemme hos deg

Vi kan komme til bilen for å teste batteriet, gi starthjelp eller bytte batteriet når forholdene gjør det mulig å arbeide trygt.

## Ofte stilte spørsmål om bilbatteri

### Betyr dårlig start alltid at batteriet må byttes?

Nei. Kulde, ladesystemet eller andre feil kan gi lignende symptomer. Batteriet bør kontrolleres før det byttes.

### Tar dere med det gamle batteriet?

Ja. Det leveres til forsvarlig gjenvinning.
MD,
                seoTitleNb: 'Bilbatteri i Tromsø – test, starthjelp og bytte | EasyTire', seoDescriptionNb: 'Batteritest, starthjelp og bytte av bilbatteri i Tromsø hjemme eller på verksted.',
                titleRu: 'Автомобильный аккумулятор в Тромсё', excerptRu: 'Диагностика аккумулятора, запуск и замена дома у клиента или в мастерской.',
                bodyRu: "## Проверка, запуск и замена\n\nОпишите проблему и укажите регистрационный номер. Мы определим, нужна ли проверка, помощь с запуском или новый аккумулятор.\n\n## Выезд к автомобилю\n\nПри безопасных условиях проверку и замену можно выполнить там, где стоит автомобиль.",
                seoTitleRu: 'Аккумулятор в Тромсё | EasyTire', seoDescriptionRu: 'Проверка, запуск и замена автомобильного аккумулятора в Тромсё.',
            ),
            $this->page(
                slug: 'bildiagnose', path: 'bildiagnose-tromso', order: 120, flow: ServiceRequestFlow::Generic,
                titleNb: 'Bildiagnose i Tromsø', excerptNb: 'Vi leser feilkoder og utfører en enkel bildiagnose hjemme hos deg eller på verkstedet.',
                bodyNb: <<<'MD'
## Hva kan en bildiagnose avdekke?

Diagnosen kan lese feilkoder fra bilens styreenheter og gi en første indikasjon på hva problemet kan skyldes, for eksempel motorlampe, varsellamper, redusert motorkraft eller elektriske feil.

## Bildiagnose hjemme eller på verksted

Beskriv problemet og oppgi registreringsnummeret, så vurderer vi om diagnosen kan utføres på stedet.

## Ofte stilte spørsmål om bildiagnose

### Finner diagnosen alltid den nøyaktige feilen?

Ikke alltid. Feilkoden viser hvilket system problemet gjelder, men videre kontroll kan være nødvendig.

### Kan dere slette feilkoder?

Ja, når det er forsvarlig. En kode bør ikke slettes før årsaken er vurdert.
MD,
                seoTitleNb: 'Bildiagnose i Tromsø – feilkoder og varsellamper | EasyTire', seoDescriptionNb: 'Lesing av feilkoder og enkel bildiagnose hjemme eller på verksted i Tromsø.',
                titleRu: 'Диагностика автомобиля в Тромсё', excerptRu: 'Читаем коды ошибок и проводим первичную диагностику дома у клиента или в мастерской.',
                bodyRu: "## Что показывает диагностика?\n\nКоды ошибок дают первичную информацию о системе, в которой возникла неисправность. Для точного вывода иногда нужна дополнительная проверка.\n\n## Дома или в мастерской\n\nОпишите симптомы и укажите регистрационный номер, чтобы мы оценили возможность выезда.",
                seoTitleRu: 'Диагностика автомобиля в Тромсё | EasyTire', seoDescriptionRu: 'Чтение ошибок и первичная диагностика автомобиля в Тромсё.',
            ),
            $this->page(
                slug: 'lyspaerer-viskerblad', path: 'bytte-lyspaerer-viskerblad-tromso', order: 130, flow: ServiceRequestFlow::Generic,
                titleNb: 'Bytte av lyspærer og viskerblad i Tromsø', excerptNb: 'Vi bytter lyspærer, viskerblad og enkelte mindre deler hjemme hos deg eller på verkstedet.',
                bodyNb: <<<'MD'
## Bytte av lyspærer, viskerblad og mindre deler

Vi kan blant annet hjelpe med nærlys, bremselys, baklys, skiltlys og viskerblad. Beskriv hva du trenger hjelp med og oppgi registreringsnummeret.

## Hjelp hjemme eller på verksted

Mange enkle utskiftninger kan gjøres hjemme når bilen er lett tilgjengelig. På enkelte bilmodeller må flere deler demonteres; da utføres arbeidet på verkstedet.

## Ofte stilte spørsmål

### Må jeg kjøpe delen selv?

Nei. Registreringsnummer og beskrivelse hjelper oss å finne en del som passer.

### Kan dere bytte LED- eller xenonlys?

Det avhenger av bilmodell og konstruksjon. Beskriv problemet, så vurderer vi arbeidet.
MD,
                seoTitleNb: 'Bytte lyspærer og viskerblad i Tromsø | EasyTire', seoDescriptionNb: 'Bytte av lyspærer, viskerblad og enkelte mindre bildeler hjemme eller på verksted i Tromsø.',
                titleRu: 'Замена ламп и щёток стеклоочистителя в Тромсё', excerptRu: 'Меняем автомобильные лампы, щётки и некоторые небольшие детали дома или в мастерской.',
                bodyRu: "## Лампы, щётки и небольшие детали\n\nОпишите, что требуется заменить, и укажите регистрационный номер. Простые работы можно выполнить на выезде, более сложный доступ потребует мастерской.",
                seoTitleRu: 'Замена ламп и щёток в Тромсё | EasyTire', seoDescriptionRu: 'Замена автомобильных ламп и щёток стеклоочистителя в Тромсё.',
            ),
        ];

        $overrides = CustomerSeoWorkbook::overrides();

        return array_map(
            fn (array $page): array => array_replace($page, $overrides[$page['slug']] ?? []),
            $pages,
        );
    }

    /** @return array<string, mixed> */
    private function page(
        string $slug,
        string $path,
        int $order,
        ServiceRequestFlow $flow,
        string $titleNb,
        string $excerptNb,
        string $bodyNb,
        string $seoTitleNb,
        string $seoDescriptionNb,
        string $titleRu,
        string $excerptRu,
        string $bodyRu,
        string $seoTitleRu,
        string $seoDescriptionRu,
    ): array {
        return [
            'slug' => $slug,
            'canonical_path' => $path,
            'sort_order' => $order,
            'publication_status' => ServicePagePublicationStatus::Published,
            'request_flow' => $flow,
            'title_nb' => $titleNb,
            'excerpt_nb' => $excerptNb,
            'body_nb' => $bodyNb,
            'seo_title_nb' => $seoTitleNb,
            'seo_description_nb' => $seoDescriptionNb,
            'title_ru' => $titleRu,
            'excerpt_ru' => $excerptRu,
            'body_ru' => $bodyRu,
            'seo_title_ru' => $seoTitleRu,
            'seo_description_ru' => $seoDescriptionRu,
        ];
    }
}
