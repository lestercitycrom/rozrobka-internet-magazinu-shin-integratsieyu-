<?php

use App\Modules\Commerce\Models\PublicServicePrice;
use App\Modules\Content\CustomerSeoWorkbook;
use App\Modules\Content\Models\ServicePage;
use Database\Seeders\SeoServicePageSeeder;
use Database\Seeders\ServicePageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([ServicePageSeeder::class, SeoServicePageSeeder::class]);
});

test('customer SEO service paths render structured copy and canonical metadata', function (): void {
    $paths = [
        'dekkskift-tromso',
        'dekkhotell-tromso',
        'dekkskift-tesla-elbil-tromso',
        'omlegging-balansering-dekk-tromso',
        'dekkreparasjon-tromso',
        'tpms-sensor-tromso',
        'oljeskift-tromso',
        'bilbatteri-tromso',
        'bildiagnose-tromso',
        'bytte-lyspaerer-viskerblad-tromso',
    ];

    foreach ($paths as $path) {
        $this->get('/'.$path)
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/'.$path).'">', false)
            ->assertSee('<h2', false);
    }
});

test('legacy service URLs redirect to their root canonical and sitemap publishes one version', function (): void {
    $this->get('/tjenester/mobilt-dekkskift')
        ->assertRedirect('/dekkskift-tromso')
        ->assertStatus(301);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee(url('/dekkskift-tromso'), false)
        ->assertDontSee(url('/tjenester/mobilt-dekkskift'), false);
});

test('SEO import is idempotent and preserves subsequent editor changes', function (): void {
    expect(ServicePage::query()->count())->toBe(13);

    ServicePage::query()->where('slug', 'dekkhotell')->update(['title_nb' => 'Redigert av EasyTire']);
    $this->seed(SeoServicePageSeeder::class);

    expect(ServicePage::query()->where('slug', 'dekkhotell')->value('title_nb'))->toBe('Redigert av EasyTire');
});

test('Markdown is rendered safely without allowing editor HTML', function (): void {
    ServicePage::query()->where('slug', 'dekkreparasjon')->update([
        'body_nb' => "## Trygg overskrift\n\n<script>alert('x')</script>\n\n[bad](javascript:alert(1))",
    ]);

    $this->get('/dekkreparasjon-tromso')
        ->assertOk()
        ->assertSee('<h2>Trygg overskrift</h2>', false)
        ->assertDontSee('<script>', false)
        ->assertDontSee('href="javascript:', false);
});

test('tire repair uses the customer approved phone call action', function (): void {
    $this->get('/dekkreparasjon-tromso')
        ->assertOk()
        ->assertSee('Ring oss')
        ->assertSee('href="tel:+4793958270"', false)
        ->assertSee('data-testid="service-hero-call"', false)
        ->assertDontSee('data-testid="service-hero-request"', false);
});

test('seasonal landing offers the two customer approved fulfilment intents directly', function (): void {
    $mobileUrl = route('storefront.requests.create', ['slug' => 'mobilt-dekkskift', 'fulfilment' => 'mobile']);
    $workshopUrl = route('storefront.requests.create', ['slug' => 'mobilt-dekkskift', 'fulfilment' => 'workshop']);

    $this->get('/dekkskift-tromso')
        ->assertOk()
        ->assertSee('Hjemme hos deg')
        ->assertSee('På vårt verksted')
        ->assertSee('href="'.e($mobileUrl).'"', false)
        ->assertSee('href="'.e($workshopUrl).'"', false)
        ->assertSee('data-testid="service-hero-mobile"', false)
        ->assertSee('data-testid="service-hero-workshop"', false);
});

test('mounting landing renders its editable price category when customer amounts are entered', function (): void {
    PublicServicePrice::create([
        'code' => 'mounting-test-price',
        'category_code' => 'mounting',
        'category_title_nb' => 'Omlegging og balansering',
        'category_title_ru' => 'Монтаж и балансировка',
        'title_nb' => 'Fire hjul',
        'title_ru' => 'Четыре колеса',
        'price_minor' => 123400,
        'currency' => 'NOK',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $this->get('/omlegging-balansering-dekk-tromso')
        ->assertOk()
        ->assertSee('data-testid="service-price-list"', false)
        ->assertSee('Omlegging og balansering')
        ->assertSee('1 234 NOK');
});

test('store and pricing landing pages are available in Norwegian and gated Russian QA', function (): void {
    $this->get('/dekk-og-felger')
        ->assertOk()
        ->assertSee('Dekk og felger til bilen din')
        ->assertSee('<link rel="canonical" href="'.route('storefront.store.index').'">', false);

    $this->get('/priser')->assertOk()->assertSee('Prislisten klargjøres');

    PublicServicePrice::create([
        'code' => 'test-price',
        'category_code' => 'test',
        'category_title_nb' => 'Testtjeneste',
        'category_title_ru' => 'Тестовая услуга',
        'title_nb' => 'Teststørrelse',
        'title_ru' => 'Тестовый размер',
        'price_minor' => 14900,
        'currency' => 'NOK',
        'is_active' => true,
        'sort_order' => 1,
    ]);
    $this->get('/priser')->assertOk()->assertSee('149 NOK')->assertSee('data-testid="public-price-list"', false);

    config()->set('storefront.ru_preview_enabled', true);
    $this->get('/ru/dekk-og-felger')->assertOk()->assertSee('Шины и диски для вашего автомобиля')->assertSee('noindex,nofollow', false);
    $this->get('/ru/dekkskift-tromso')->assertOk()->assertSee('Замена колёс в Тромсё')->assertSee(route('storefront.seo-services.show', 'dekkskift-tromso'), false);
    $this->get('/ru/priser')->assertOk()->assertSee('Понятные цены без догадок')->assertSee('noindex,nofollow', false);
});

test('a published CMS canonical path works without a code whitelist and shop alias is permanent', function (): void {
    $source = ServicePage::query()->where('canonical_path', 'dekkskift-tromso')->firstOrFail();
    $copy = $source->replicate();
    $copy->slug = 'ny-cms-side';
    $copy->canonical_path = 'ny-cms-side-tromso';
    $copy->title_nb = 'Ny CMS-side';
    $copy->save();

    $this->get('/ny-cms-side-tromso')->assertOk()->assertSee('Ny CMS-side');
    $this->get('/shop')->assertStatus(301)->assertRedirect('/dekk-og-felger');

    config()->set('storefront.ru_preview_enabled', true);
    $this->get('/ru/ny-cms-side-tromso')->assertOk();
    $this->get('/ru/shop')->assertStatus(301)->assertRedirect('/ru/dekk-og-felger');
});

test('customer approved SEO workbook metadata and long form copy remain exact', function (): void {
    $descriptions = [
        'mobilt-dekkskift' => 'Bestill dekkskift der bilen står – hjemme eller på arbeidsplassen – eller kom til vårt verksted i Tromsø. Vi gjør hjulskiftet raskt og profesjonelt på tidspunktet som passer deg.',
        'dekkhotell' => 'Dekkhotell i Tromsø med dekkskift hjemme, på jobb eller på verksted. Trygg oppbevaring av hjul mellom sesongene. Se pris og bestill online.',
        'tesla-og-elbil' => 'Bestill dekkskift for Tesla og andre elbiler i Tromsø. Vi bruker egnet løfteutstyr, riktige løftepunkter og utfører hjulskift hjemme, på jobb eller på verksted.',
        'omlegging-balansering' => 'Profesjonell omlegging og balansering av dekk i Tromsø. Vi monterer dekk på felg, balanserer hjul og kontrollerer at hjulene er klare for trygg kjøring.',
        'dekkreparasjon' => 'Har du punktert dekk? EasyTire tilbyr kontroll og reparasjon av bildekk i Tromsø når skaden kan repareres på en trygg og forsvarlig måte.',
        'tpms-sensor' => 'Lyser dekktrykkslampen? Vi hjelper med kontroll, service og bytte av TPMS-sensorer og dekktrykksensorer i Tromsø.',
        'oljeskift' => 'Bestill oljeskift i Tromsø hjemme eller på vårt verksted. Vi skifter motorolje og oljefilter, med mulighet for å legge til luftfilter og kupefilter.',
        'bilbatteri' => 'Vi tilbyr batteritest, starthjelp og bytte av bilbatteri i Tromsø. Bestill hjemme hos deg eller på vårt verksted.',
        'bildiagnose' => 'Har motorlampen eller en varsellampe begynt å lyse? Vi leser feilkoder og utfører enkel bildiagnose hjemme hos deg eller på vårt verksted i Tromsø.',
        'lyspaerer-viskerblad' => 'Vi bytter lyspærer, viskerblad og utfører enkelte mindre arbeider på bilen hjemme hos deg eller på vårt verksted i Tromsø.',
    ];

    expect(CustomerSeoWorkbook::overrides())->toHaveCount(10);
    foreach ($descriptions as $slug => $description) {
        $page = ServicePage::query()->where('slug', $slug)->firstOrFail();
        expect($page->seo_description_nb)->toBe($description)
            ->and(mb_strlen((string) $page->body_nb))->toBeGreaterThan(500);
        $this->get('/'.$page->canonical_path)
            ->assertOk()
            ->assertSee('name="description" content="'.e($description).'"', false);
    }

    $this->get('/dekkskift-tromso')->assertSee('Hva må være klart før dere kommer?');
    $this->get('/dekkhotell-tromso')->assertSee('Hva skjer neste sesong?');
    $this->get('/tpms-sensor-tromso')->assertSee('Hvor lenge varer en TPMS-sensor?');
    $this->get('/oljeskift-tromso')->assertSee('10 000–12 000 kilometer');
});
