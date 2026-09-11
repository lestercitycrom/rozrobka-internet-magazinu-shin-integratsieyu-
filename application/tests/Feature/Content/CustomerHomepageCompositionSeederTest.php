<?php

use App\Modules\Commerce\Models\PublicServicePrice;
use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Models\HomepageBusinessPartner;
use App\Modules\Content\Models\MediaAsset;
use App\Modules\Content\Models\StorefrontSettings;
use Database\Seeders\CustomerHomepageCompositionSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PublicServicePriceSeeder;
use Database\Seeders\RollbackCustomerHomepageCompositionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
});

test('issue 47 manifest is idempotent and installs only the guarded base hotel price and four local partners', function (): void {
    $this->seed(PublicServicePriceSeeder::class);
    $this->seed(CustomerHomepageCompositionSeeder::class);
    $this->seed(CustomerHomepageCompositionSeeder::class);

    $hotel = PublicServicePrice::query()->where('code', 'hotel-r15')->firstOrFail();
    expect($hotel->price_minor)->toBe(70000)
        ->and($hotel->currency)->toBe('NOK')
        ->and($hotel->manifest_hash)->toBe(CustomerHomepageCompositionSeeder::HOTEL_PRICE_MANIFEST)
        ->and(PublicServicePrice::query()->where('code', 'hotel-r16-r18')->value('price_minor'))->toBe(85000)
        ->and(HomepageBusinessPartner::query()->count())->toBe(4)
        ->and(MediaAsset::query()->where('path', 'like', 'media/homepage/issue-47/%')->count())->toBe(4);

    expect(HomepageBusinessPartner::query()->orderBy('sort_order')->pluck('name')->all())
        ->toBe(['Nordic Spas', 'Element NOR', 'Carsoe', 'Avis']);
});

test('issue 47 manifest preserves operator copy partner and price edits', function (): void {
    $this->seed(PublicServicePriceSeeder::class);
    $this->seed(CustomerHomepageCompositionSeeder::class);

    $settings = StorefrontSettings::query()->findOrFail(1);
    $settings->update(['business_title_nb' => 'Operatørens egen bedriftsoverskrift']);

    $partner = HomepageBusinessPartner::query()->where('content_key', 'issue-47-avis')->firstOrFail();
    $partner->update(['name' => 'Operatørens Avis-navn', 'manifest_hash' => null]);

    $hotel = PublicServicePrice::query()->where('code', 'hotel-r15')->firstOrFail();
    $hotel->update(['price_minor' => 73000, 'manifest_hash' => null]);

    $this->seed(CustomerHomepageCompositionSeeder::class);

    expect(StorefrontSettings::query()->findOrFail(1)->business_title_nb)
        ->toBe('Operatørens egen bedriftsoverskrift')
        ->and($partner->fresh()->name)->toBe('Operatørens Avis-navn')
        ->and($partner->fresh()->manifest_hash)->toBeNull()
        ->and($hotel->fresh()->price_minor)->toBe(73000)
        ->and($hotel->fresh()->manifest_hash)->toBeNull();
});

test('issue 47 rollback drafts untouched partners and restores only its own hotel price', function (): void {
    $this->seed(PublicServicePriceSeeder::class);
    $this->seed(CustomerHomepageCompositionSeeder::class);
    $this->seed(RollbackCustomerHomepageCompositionSeeder::class);

    expect(PublicServicePrice::query()->where('code', 'hotel-r15')->value('price_minor'))->toBe(75000)
        ->and(PublicServicePrice::query()->where('code', 'hotel-r15')->value('manifest_hash'))->toBeNull()
        ->and(HomepageBusinessPartner::query()->where('publication_status', ContentPageStatus::Draft->value)->count())->toBe(4)
        ->and(MediaAsset::query()->where('path', 'like', 'media/homepage/issue-47/%')->count())->toBe(4);
});

test('homepage renders the final approved order copy links logos and hidden Russian preview', function (): void {
    $this->seed(DatabaseSeeder::class);

    $response = $this->get('/')->assertOk();
    $response
        ->assertSeeInOrder([
            'data-testid="home-hero"',
            'data-testid="home-dekkhotell"',
            'data-testid="homepage-promotions"',
            'data-testid="homepage-additional-services"',
            'data-testid="home-process"',
            'data-testid="home-prices"',
            'data-testid="homepage-wheel-services"',
            'data-testid="homepage-reviews"',
            'class="et-v3-contact"',
        ], false)
        ->assertDontSee('data-testid="catalog-foundation"', false)
        ->assertSee('Dekkhotell uten stress')
        ->assertSee('Vi kommer til deg')
        ->assertSee('700 kr')
        ->assertSee('Google Maps viser riktig innkjøring via Ringvegen 202.')
        ->assertSee('https://www.nordicspas.no/', false)
        ->assertSee('https://elementnor.no/', false)
        ->assertSee('https://carsoe.com/', false)
        ->assertSee('https://www.avis.no/', false)
        ->assertSee('data-dekkhotell-dialog', false)
        ->assertDontSee('data-homepage-video-element', false);

    config()->set('storefront.ru_preview_enabled', true);
    $this->get('/ru')
        ->assertOk()
        ->assertSee('Шинный отель без хлопот')
        ->assertSee('Мы приедем к вам')
        ->assertSee('Google Maps показывает правильный въезд через Ringvegen 202.')
        ->assertDontSee('data-testid="catalog-foundation"', false);
});
