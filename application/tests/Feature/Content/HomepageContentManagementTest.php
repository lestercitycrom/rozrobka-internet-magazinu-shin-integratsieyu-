<?php

use App\Models\User;
use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Models\HomepagePromotion;
use App\Modules\Content\Models\HomepageReview;
use App\Modules\Content\Models\MediaAsset;
use Database\Seeders\HomepageContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('homepage seed publishes the current offer and source-backed customer reviews', function (): void {
    $this->seed(HomepageContentSeeder::class);

    expect(HomepagePromotion::query()->published()->count())->toBe(1)
        ->and(HomepageReview::query()->published()->count())->toBe(3);

    $this->get('/')
        ->assertOk()
        ->assertSee('Dekkskift på verksted fra 550 kr')
        ->assertSee('Solveig Lundin')
        ->assertSee('data-testid="business-contact-card"', false);
});

test('homepage follows the approved compact conversion order without duplicate story or trust blocks', function (): void {
    $response = $this->get('/')->assertOk();

    $response
        ->assertSeeInOrder([
            'class="et-v3-hero"',
            'data-testid="home-dekkhotell"',
            'data-testid="home-process"',
            'data-testid="home-prices"',
        ], false)
        ->assertDontSee('class="et-v3-trust', false)
        ->assertDontSee('class="et-v3-story', false);

    expect(substr_count($response->getContent(), 'et-v3-hero__primary-action'))->toBe(1);
});

test('homepage keeps draft promotions and all empty reviews out of public output while retaining the honest B2B contact CTA', function (): void {
    HomepagePromotion::create(homepagePromotionPayload(['publication_status' => ContentPageStatus::Draft, 'title_nb' => 'Ikke publisert tilbud']));

    $this->get('/')
        ->assertOk()
        ->assertSee('data-testid="business-contact-card"', false)
        ->assertDontSee('data-testid="homepage-promotions"', false)
        ->assertDontSee('data-testid="homepage-reviews"', false)
        ->assertDontSee('Ikke publisert tilbud');
});

test('homepage renders only published localized promotions and source-backed reviews', function (): void {
    HomepagePromotion::create(homepagePromotionPayload());
    HomepageReview::create(homepageReviewPayload());

    $this->get('/')
        ->assertOk()
        ->assertSee('data-testid="homepage-promotions"', false)
        ->assertSee('Dekkhotell ved sesongskift')
        ->assertSee('href="/tjenester"', false)
        ->assertSee('data-testid="homepage-reviews"', false)
        ->assertSee('God oppfølging')
        ->assertSee('href="https://reviews.example.test/easytire"', false);

    config()->set('storefront.ru_preview_enabled', true);
    $this->get('/ru')
        ->assertOk()
        ->assertSee('Хранение при сезонной замене')
        ->assertSee('Хорошая обратная связь')
        ->assertSee('noindex,nofollow', false);
});

test('administrator can manage promotions and cannot publish a review without an approved source URL', function (): void {
    $admin = User::factory()->operations()->create();
    $promotionPayload = homepagePromotionPayload(['publication_status' => ContentPageStatus::Published->value]);

    $this->actingAs($admin)->get('/admin/homepage-promotions/create')->assertOk();
    $this->actingAs($admin)->post('/admin/homepage-promotions', $promotionPayload)->assertRedirect();
    $promotion = HomepagePromotion::query()->firstOrFail();
    $this->actingAs($admin)->put('/admin/homepage-promotions/'.$promotion->id, [...$promotionPayload, 'cta_url' => 'javascript:alert(1)'])->assertSessionHasErrors('cta_url');

    $this->actingAs($admin)->get('/admin/homepage-reviews/create')->assertOk();
    $this->actingAs($admin)->post('/admin/homepage-reviews', [...homepageReviewPayload(), 'source_url' => ''])->assertSessionHasErrors('source_url');
    $this->actingAs($admin)->post('/admin/homepage-reviews', homepageReviewPayload())->assertRedirect();
    $review = HomepageReview::query()->firstOrFail();
    $this->actingAs($admin)->delete('/admin/homepage-reviews/'.$review->id)->assertRedirect();
});

test('promotions accept only active image assets and safely lose their media when it is deactivated', function (): void {
    $admin = User::factory()->operations()->create();
    $image = homepagePromotionMedia($admin, 'promotion-image.webp', 'image/webp', true);
    $document = homepagePromotionMedia($admin, 'promotion-document.pdf', 'application/pdf', true);
    $payload = homepagePromotionPayload([
        'media_asset_id' => $image->id,
        'publication_status' => ContentPageStatus::Published->value,
    ]);

    $this->actingAs($admin)
        ->get('/admin/homepage-promotions/create')
        ->assertOk()
        ->assertSee('promotion-media', false)
        ->assertSee('promotion-image.webp');

    $this->actingAs($admin)
        ->post('/admin/homepage-promotions', $payload)
        ->assertRedirect();

    $promotion = HomepagePromotion::query()->firstOrFail();
    expect($promotion->media_asset_id)->toBe($image->id);

    $this->actingAs($admin)
        ->put('/admin/homepage-promotions/'.$promotion->id, [...$payload, 'media_asset_id' => $document->id])
        ->assertSessionHasErrors('media_asset_id');

    $image->update(['is_active' => false]);
    expect($promotion->fresh()->load('mediaAsset')->mediaAsset)->toBeNull();
});

function homepagePromotionMedia(User $admin, string $name, string $mime, bool $active): MediaAsset
{
    return MediaAsset::query()->create([
        'id' => (string) Str::ulid(),
        'disk' => 'public',
        'path' => 'content/'.$name,
        'original_name' => $name,
        'mime' => $mime,
        'byte_size' => 1,
        'width' => 1600,
        'height' => 900,
        'sha256' => hash('sha256', $name),
        'uploader_id' => $admin->id,
        'is_active' => $active,
    ]);
}

function homepagePromotionPayload(array $overrides = []): array
{
    return [...[
        'sort_order' => 1,
        'publication_status' => ContentPageStatus::Published,
        'title_nb' => 'Dekkhotell ved sesongskift',
        'body_nb' => 'Avklar innhold og vilkår med EasyTire.',
        'cta_label_nb' => 'Se tjenester',
        'title_ru' => 'Хранение при сезонной замене',
        'body_ru' => 'Условия согласует EasyTire.',
        'cta_label_ru' => 'Посмотреть услуги',
        'cta_url' => '/tjenester',
    ], ...$overrides];
}

function homepageReviewPayload(array $overrides = []): array
{
    return [...[
        'sort_order' => 1,
        'publication_status' => ContentPageStatus::Published->value,
        'quote_nb' => 'God oppfølging',
        'author_nb' => 'Kunde',
        'source_label_nb' => 'Les omtalen',
        'quote_ru' => 'Хорошая обратная связь',
        'author_ru' => 'Клиент',
        'source_label_ru' => 'Открыть отзыв',
        'source_url' => 'https://reviews.example.test/easytire',
    ], ...$overrides];
}
