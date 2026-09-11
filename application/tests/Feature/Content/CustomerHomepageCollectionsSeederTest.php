<?php

use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Enums\HomepageServiceCollection;
use App\Modules\Content\Models\HomepagePromotion;
use App\Modules\Content\Models\HomepageServiceCard;
use App\Modules\Content\Models\MediaAsset;
use Database\Seeders\CustomerHomepageCollectionsSeeder;
use Database\Seeders\HomepageContentSeeder;
use Database\Seeders\RollbackCustomerHomepageCollectionsSeeder;
use Database\Seeders\SeoServicePageSeeder;
use Database\Seeders\ServicePageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->seed([ServicePageSeeder::class, SeoServicePageSeeder::class]);
});

test('customer homepage manifest seeds the exact four offers and two independent six-card collections idempotently', function (): void {
    $this->seed(HomepageContentSeeder::class);
    $this->seed(CustomerHomepageCollectionsSeeder::class);
    $this->seed(CustomerHomepageCollectionsSeeder::class);

    expect(HomepagePromotion::query()->count())->toBe(4)
        ->and(HomepagePromotion::query()->whereNotNull('manifest_hash')->count())->toBe(4)
        ->and(HomepageServiceCard::query()->where('collection', HomepageServiceCollection::Additional->value)->count())->toBe(6)
        ->and(HomepageServiceCard::query()->where('collection', HomepageServiceCollection::Wheels->value)->count())->toBe(6)
        ->and(HomepageServiceCard::query()->whereNotNull('manifest_hash')->count())->toBe(12);

    $adoptedWorkshop = HomepagePromotion::query()->where('content_key', 'issue-46-offer-workshop-wheel-change')->firstOrFail();
    expect($adoptedWorkshop->short_body_nb)->toBe('Raskt og enkelt på verkstedet vårt i Ringvegen 200.')
        ->and($adoptedWorkshop->publication_status)->toBe(ContentPageStatus::Published)
        ->and($adoptedWorkshop->manifest_hash)->not->toBeNull();

    expect(HomepagePromotion::query()->orderBy('sort_order')->pluck('title_nb')->all())->toBe([
        'Bestill i god tid – spar opptil 100 kr',
        'Flere biler på samme adresse',
        'Gratis henting til dekkhotellet',
        'Dekkskift på verksted – kun fra 550 kr',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder([
            'data-testid="home-dekkhotell"',
            'data-testid="homepage-promotions"',
            'data-testid="homepage-additional-services"',
            'data-testid="home-process"',
            'data-testid="home-prices"',
            'data-testid="homepage-wheel-services"',
        ], false)
        ->assertSee('data-total="4"', false);
});

test('workshop adoption preserves every operator-owned mismatch without creating a duplicate offer', function (string $field, mixed $value): void {
    $legacy = legacyWorkshopPromotion();
    $legacy->forceFill([$field => $value])->save();

    $this->seed(CustomerHomepageCollectionsSeeder::class);
    $this->seed(CustomerHomepageCollectionsSeeder::class);

    $preserved = HomepagePromotion::query()->where('content_key', 'issue-46-offer-workshop-wheel-change')->firstOrFail();
    expect($preserved->getRawOriginal($field))->toBe($value)
        ->and($preserved->manifest_hash)->toBeNull()
        ->and(HomepagePromotion::query()->count())->toBe(4)
        ->and(HomepagePromotion::query()->where('content_key', 'issue-46-offer-workshop-wheel-change')->count())->toBe(1);
})->with([
    'draft publication' => ['publication_status', ContentPageStatus::Draft->value],
    'Russian body' => ['body_ru', 'Текст изменён оператором.'],
    'Norwegian CTA' => ['cta_label_nb', 'Operatørens lenke'],
    'Russian CTA' => ['cta_label_ru', 'Ссылка оператора'],
    'order' => ['sort_order', 77],
    'destination' => ['cta_url', '/kontakt'],
]);

test('workshop adoption preserves an operator-selected media asset and rollback does not touch the conflict', function (): void {
    $asset = MediaAsset::query()->create([
        'id' => (string) Str::ulid(),
        'disk' => 'public',
        'path' => 'media/operator-workshop.webp',
        'original_name' => 'operator-workshop.webp',
        'mime' => 'image/webp',
        'byte_size' => 1,
        'width' => 1200,
        'height' => 900,
        'sha256' => hash('sha256', 'operator-workshop'),
        'is_active' => true,
    ]);
    $legacy = legacyWorkshopPromotion();
    $legacy->update(['media_asset_id' => $asset->id]);

    $this->seed(CustomerHomepageCollectionsSeeder::class);
    $this->seed(RollbackCustomerHomepageCollectionsSeeder::class);

    $preserved = HomepagePromotion::query()->where('content_key', 'issue-46-offer-workshop-wheel-change')->firstOrFail();
    expect($preserved->media_asset_id)->toBe($asset->id)
        ->and($preserved->manifest_hash)->toBeNull()
        ->and($preserved->publication_status)->toBe(ContentPageStatus::Published);
});

test('a fully edited sole legacy workshop offer is adopted without being overwritten or duplicated', function (): void {
    $legacy = legacyWorkshopPromotion([
        'sort_order' => 91,
        'publication_status' => ContentPageStatus::Draft,
        'title_nb' => 'Operatørens helt nye tilbud',
        'short_body_nb' => 'Operatørens korte tekst.',
        'body_nb' => 'Operatørens fullstendige vilkår.',
        'cta_label_nb' => 'Egen handling',
        'title_ru' => 'Полностью изменённое предложение',
        'short_body_ru' => 'Свой короткий текст.',
        'body_ru' => 'Собственные полные условия.',
        'cta_label_ru' => 'Своя кнопка',
        'cta_url' => '/kontakt',
    ]);

    // This legacy seeder used to recreate the default workshop offer here
    // because the operator-owned URL no longer matched its lookup.
    $this->seed(HomepageContentSeeder::class);
    expect(HomepagePromotion::query()->count())->toBe(1);

    $this->seed(CustomerHomepageCollectionsSeeder::class);
    $this->seed(CustomerHomepageCollectionsSeeder::class);

    $preserved = HomepagePromotion::query()
        ->where('content_key', 'issue-46-offer-workshop-wheel-change')
        ->firstOrFail();

    expect($preserved->id)->toBe($legacy->id)
        ->and($preserved->sort_order)->toBe(91)
        ->and($preserved->publication_status)->toBe(ContentPageStatus::Draft)
        ->and($preserved->title_nb)->toBe('Operatørens helt nye tilbud')
        ->and($preserved->short_body_nb)->toBe('Operatørens korte tekst.')
        ->and($preserved->body_nb)->toBe('Operatørens fullstendige vilkår.')
        ->and($preserved->cta_label_nb)->toBe('Egen handling')
        ->and($preserved->title_ru)->toBe('Полностью изменённое предложение')
        ->and($preserved->short_body_ru)->toBe('Свой короткий текст.')
        ->and($preserved->body_ru)->toBe('Собственные полные условия.')
        ->and($preserved->cta_label_ru)->toBe('Своя кнопка')
        ->and($preserved->cta_url)->toBe('/kontakt')
        ->and($preserved->manifest_hash)->toBeNull()
        ->and(HomepagePromotion::query()->count())->toBe(4);
});

test('legacy published promotions without short copy remain readable while drafts stay hidden', function (): void {
    HomepagePromotion::query()->create([
        'sort_order' => 1,
        'publication_status' => ContentPageStatus::Published,
        'title_nb' => 'Eldre manuelt tilbud',
        'short_body_nb' => null,
        'body_nb' => 'Hele det eldre norske tilbudet er fortsatt synlig.',
        'cta_label_nb' => 'Kontakt oss',
        'title_ru' => 'Старое ручное предложение',
        'short_body_ru' => null,
        'body_ru' => 'Полный старый русский текст по-прежнему виден.',
        'cta_label_ru' => 'Связаться с нами',
        'cta_url' => '/kontakt',
    ]);
    HomepagePromotion::query()->create([
        'sort_order' => 2,
        'publication_status' => ContentPageStatus::Draft,
        'title_nb' => 'Skjult eldre tilbud',
        'body_nb' => 'Denne teksten må ikke publiseres.',
        'cta_label_nb' => 'Kontakt oss',
        'title_ru' => 'Скрытое старое предложение',
        'body_ru' => 'Этот текст нельзя публиковать.',
        'cta_label_ru' => 'Связаться с нами',
        'cta_url' => '/kontakt',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Hele det eldre norske tilbudet er fortsatt synlig.')
        ->assertDontSee('Denne teksten må ikke publiseres.');

    config()->set('storefront.ru_preview_enabled', true);
    $this->get('/ru')
        ->assertOk()
        ->assertSee('Полный старый русский текст по-прежнему виден.')
        ->assertDontSee('Этот текст нельзя публиковать.');
});

test('seeded homepage service destinations resolve to the existing NB and RU targets', function (): void {
    $this->seed(CustomerHomepageCollectionsSeeder::class);

    $this->get('/')
        ->assertOk()
        ->assertSee('href="/tjenester#service-interior-cleaning"', false)
        ->assertSee('href="/tjenester#service-polishing-paint"', false)
        ->assertSee('href="'.route('storefront.services.show', 'bobil-og-varebil').'"', false);
    $this->get('/tjenester')
        ->assertOk()
        ->assertSee('id="service-interior-cleaning"', false)
        ->assertSee('id="service-polishing-paint"', false);
    $this->get('/tjenester/bobil-og-varebil')->assertOk();

    config()->set('storefront.ru_preview_enabled', true);
    $this->get('/ru')
        ->assertOk()
        ->assertSee('href="/ru/tjenester#service-interior-cleaning"', false)
        ->assertSee('href="/ru/tjenester#service-polishing-paint"', false)
        ->assertSee('href="'.route('storefront.preview.services.show', 'bobil-og-varebil').'"', false);
    $this->get('/ru/tjenester/bobil-og-varebil')->assertOk();
});

test('manifest preserves operator edits and soft-deleted rows instead of recreating or overwriting them', function (): void {
    $this->seed(CustomerHomepageCollectionsSeeder::class);
    $card = HomepageServiceCard::query()->where('content_key', 'issue-46-additional-oil-change')->firstOrFail();
    $promotion = HomepagePromotion::query()->where('content_key', 'issue-46-offer-early-booking')->firstOrFail();

    $card->update(['title_nb' => 'Redigert av operatør', 'manifest_hash' => null]);
    $promotion->delete();
    $this->seed(CustomerHomepageCollectionsSeeder::class);

    expect($card->fresh()->title_nb)->toBe('Redigert av operatør')
        ->and($card->fresh()->manifest_hash)->toBeNull()
        ->and(HomepagePromotion::withTrashed()->where('content_key', $promotion->content_key)->count())->toBe(1)
        ->and(HomepagePromotion::query()->where('content_key', $promotion->content_key)->exists())->toBeFalse();
});

test('rollback unpublishes untouched manifest rows while preserving edited rows and all content', function (): void {
    $this->seed(CustomerHomepageCollectionsSeeder::class);
    $edited = HomepageServiceCard::query()->where('content_key', 'issue-46-additional-oil-change')->firstOrFail();
    $edited->update(['title_nb' => 'Operatørbeholdt', 'manifest_hash' => null]);

    $this->seed(RollbackCustomerHomepageCollectionsSeeder::class);

    expect(HomepagePromotion::query()->count())->toBe(4)
        ->and(HomepagePromotion::query()->where('publication_status', ContentPageStatus::Draft->value)->count())->toBe(4)
        ->and(HomepageServiceCard::query()->count())->toBe(12)
        ->and(HomepageServiceCard::query()->whereNotNull('manifest_hash')->where('publication_status', ContentPageStatus::Draft->value)->count())->toBe(11)
        ->and($edited->fresh()->publication_status)->toBe(ContentPageStatus::Published)
        ->and($edited->fresh()->title_nb)->toBe('Operatørbeholdt');
});

/** @param array<string, mixed> $overrides */
function legacyWorkshopPromotion(array $overrides = []): HomepagePromotion
{
    return HomepagePromotion::query()->create([...[
        'sort_order' => 10,
        'publication_status' => ContentPageStatus::Published,
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
    ], ...$overrides]);
}
