<?php

use App\Modules\Commerce\Models\PublicServicePrice;
use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Models\ContentPage;
use App\Modules\Content\Models\HomepagePromotion;
use App\Modules\Content\Models\ServicePage;
use App\Modules\Content\Models\StorefrontSettings;
use Database\Seeders\ContentPageSeeder;
use Database\Seeders\SeoServicePageSeeder;
use Database\Seeders\ServicePageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([ContentPageSeeder::class, ServicePageSeeder::class, SeoServicePageSeeder::class]);
});

test('header search submits to the shared site results and keeps tyre finder access', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('action="'.route('storefront.site-search.index').'"', false)
        ->assertSee('data-site-search', false)
        ->assertSee('href="'.route('storefront.site-search.index').'"', false)
        ->assertSee('For eksempel pris, oljeskift eller dekkhotell')
        ->assertSee('href="'.route('storefront.store.index').'"', false)
        ->assertDontSee('data-catalog-search', false);

    config()->set('storefront.ru_preview_enabled', true);
    $this->get('/ru')
        ->assertOk()
        ->assertSee('action="'.route('storefront.preview.site-search.index').'"', false)
        ->assertSee('Например: цена, замена масла или шинный отель');
});

test('customer example terms find prices oil change and tyre hotel without searching the catalogue', function (): void {
    foreach ([
        'price' => route('storefront.pricing.index'),
        'oil change' => route('storefront.seo-services.show', 'oljeskift-tromso'),
        'dekkhotell' => route('storefront.seo-services.show', 'dekkhotell-tromso'),
    ] as $query => $expectedUrl) {
        $this->get('/sok?q='.urlencode($query))
            ->assertOk()
            ->assertSee('data-testid="site-search-results"', false)
            ->assertSee('href="'.$expectedUrl.'"', false);
    }
});

test('service directory fallbacks remain searchable when there is no standalone page', function (): void {
    expect(ServicePage::query()->where('slug', 'interiorrens')->exists())->toBeFalse();

    $this->get('/sok?q='.urlencode('interior cleaning'))
        ->assertOk()
        ->assertSee('Interiørrens')
        ->assertSee('href="'.route('storefront.services.index').'#service-interior-cleaning"', false);
});

test('Russian search uses localized content and routes', function (): void {
    config()->set('storefront.ru_preview_enabled', true);

    $this->get('/ru/sok?q='.urlencode('замена масла'))
        ->assertOk()
        ->assertSee('Замена масла в Тромсё')
        ->assertSee('href="'.route('storefront.preview.seo-services.show', 'oljeskift-tromso').'"', false)
        ->assertSee('data-testid="quick-finder"', false);
});

test('invalid structured query input is treated as an empty search', function (): void {
    $this->get('/sok?q[]=price')
        ->assertOk()
        ->assertSee('Søk etter det du trenger')
        ->assertDontSee('data-testid="site-search-results"', false);
});

test('only routable published localized content is exposed', function (): void {
    ContentPage::query()->create([
        'slug' => 'ikke-offentlig-rute',
        'sort_order' => 999,
        'publication_status' => ContentPageStatus::Published,
        'title_nb' => 'Unik skjult rute',
        'excerpt_nb' => 'Skal ikke indekseres.',
        'body_nb' => 'hemmelig-ruteord',
        'meta_title_nb' => 'Skjult',
        'meta_description_nb' => 'Skjult',
        'title_ru' => 'Скрыто',
        'excerpt_ru' => 'Скрыто',
        'body_ru' => 'Скрыто',
        'meta_title_ru' => 'Скрыто',
        'meta_description_ru' => 'Скрыто',
    ]);
    ServicePage::query()->create([
        'slug' => 'draft-secret',
        'sort_order' => 999,
        'publication_status' => ServicePagePublicationStatus::Draft,
        'title_nb' => 'Utkast hemmelig',
        'excerpt_nb' => 'draft-only-needle',
        'body_nb' => 'draft-only-needle',
        'seo_title_nb' => 'Utkast',
        'seo_description_nb' => 'Utkast',
        'title_ru' => 'Черновик',
        'excerpt_ru' => 'Черновик',
        'body_ru' => 'Черновик',
        'seo_title_ru' => 'Черновик',
        'seo_description_ru' => 'Черновик',
    ]);

    $this->get('/sok?q=hemmelig-ruteord')->assertOk()->assertSee('data-testid="site-search-empty"', false);
    $this->get('/sok?q=draft-only-needle')->assertOk()->assertSee('data-testid="site-search-empty"', false);
});

test('published homepage offers link to their real homepage section while drafts stay hidden', function (): void {
    foreach ([ContentPageStatus::Published, ContentPageStatus::Draft] as $status) {
        $published = $status === ContentPageStatus::Published;
        HomepagePromotion::query()->create([
            'content_key' => $published ? 'search-visible-offer' : 'search-draft-offer',
            'sort_order' => $published ? 1 : 2,
            'publication_status' => $status,
            'title_nb' => $published ? 'Polarnatt kampanje' : 'Hemmelig kampanje',
            'short_body_nb' => $published ? 'polarnatt-unik-tilbud' : 'draft-unik-tilbud',
            'body_nb' => $published ? 'Et synlig tilbud.' : 'Et utkast.',
            'cta_label_nb' => 'Les mer',
            'title_ru' => $published ? 'Акция полярной ночи' : 'Скрытая акция',
            'short_body_ru' => $published ? 'polarnatt-unik-tilbud' : 'draft-unik-tilbud',
            'body_ru' => $published ? 'Видимая акция.' : 'Черновик.',
            'cta_label_ru' => 'Подробнее',
            'cta_url' => '/priser',
        ]);
    }

    $this->get('/sok?q=polarnatt-unik-tilbud')
        ->assertOk()
        ->assertSee('Polarnatt kampanje')
        ->assertSee('href="'.route('storefront.home').'#aktuelle-tilbud"', false);
    $this->get('/sok?q=draft-unik-tilbud')->assertOk()->assertSee('data-testid="site-search-empty"', false);
});

test('current managed homepage section copy is searchable', function (): void {
    StorefrontSettings::query()->updateOrCreate(['id' => 1], [
        'logistics_title_nb' => 'Eksakt vinterlogistikk',
        'hotel_text_nb' => 'polarnatt-lagring med transport',
    ]);

    $this->get('/sok?q=polarnatt-lagring')
        ->assertOk()
        ->assertSee('Eksakt vinterlogistikk')
        ->assertSee('href="'.route('storefront.home').'#home-logistics-title"', false);
});

test('price search anchors resolve to rendered published rows', function (): void {
    PublicServicePrice::query()->create([
        'code' => 'Search Price 17',
        'category_code' => 'workshop',
        'category_title_nb' => 'Verksted',
        'category_title_ru' => 'Мастерская',
        'title_nb' => 'Nordlysbalansering',
        'title_ru' => 'Балансировка Северное сияние',
        'description_nb' => 'unik-prisrad',
        'description_ru' => 'unik-prisrad',
        'price_minor' => 99000,
        'currency' => 'NOK',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $this->get('/sok?q=unik-prisrad')
        ->assertOk()
        ->assertSee('href="'.route('storefront.pricing.index').'#price-search-price-17"', false);
    $this->get('/priser')
        ->assertOk()
        ->assertSee('id="price-search-price-17"', false);
});
