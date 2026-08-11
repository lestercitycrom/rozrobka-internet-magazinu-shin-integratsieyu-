<?php

use App\Models\User;
use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Models\HomepagePromotion;
use App\Modules\Content\Models\HomepageReview;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
