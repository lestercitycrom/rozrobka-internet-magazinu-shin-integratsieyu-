<?php

use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Enums\HomepageServiceCollection;
use App\Modules\Content\Models\HomepagePromotion;
use App\Modules\Content\Models\HomepageServiceCard;
use Database\Seeders\CustomerHomepageCollectionsSeeder;
use Database\Seeders\HomepageContentSeeder;
use Database\Seeders\RollbackCustomerHomepageCollectionsSeeder;
use Database\Seeders\SeoServicePageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->seed(SeoServicePageSeeder::class);
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
