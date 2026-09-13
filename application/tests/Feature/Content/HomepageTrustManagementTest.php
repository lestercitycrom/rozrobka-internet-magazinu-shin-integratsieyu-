<?php

use App\Models\User;
use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Enums\ReviewPlatform;
use App\Modules\Content\Models\HomepageBusinessPartner;
use App\Modules\Content\Models\HomepageRatingSummary;
use App\Modules\Content\Models\MediaAsset;
use Database\Seeders\HomepageContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('confirmed Facebook and Google aggregate ratings are seeded with localized labels and source links', function (): void {
    $this->seed(HomepageContentSeeder::class);

    expect(HomepageRatingSummary::query()->published()->count())->toBe(2)
        ->and(HomepageRatingSummary::query()->where('platform', ReviewPlatform::Facebook)->value('rating'))->toBe('5.0')
        ->and(HomepageRatingSummary::query()->where('platform', ReviewPlatform::Google)->value('rating'))->toBe('5.0')
        ->and(HomepageBusinessPartner::query()->count())->toBe(0);

    $this->get('/')
        ->assertOk()
        ->assertSee('data-testid="homepage-rating-summaries"', false)
        ->assertSee('data-testid="homepage-rating-summary"', false)
        ->assertSee('30+ omtaler')
        ->assertSee('Omtrent 30 omtaler')
        ->assertSee(e(config('storefront.facebook_url')), false)
        ->assertSee(e(config('storefront.map_url')), false)
        ->assertDontSee('data-testid="homepage-business-partners"', false);

    config()->set('storefront.ru_preview_enabled', true);
    $this->get('/ru')
        ->assertOk()
        ->assertSee('30+ отзывов')
        ->assertSee('Около 30 отзывов')
        ->assertSee('Все отзывы в Google');
});

test('public homepage hides draft and unsafe ratings and only renders published partners backed by active media', function (): void {
    $uploader = User::factory()->owner()->create();
    $active = homepageTrustAsset($uploader, 'active', true);
    $inactive = homepageTrustAsset($uploader, 'inactive', false);

    HomepageRatingSummary::create(homepageTrustRatingPayload([
        'platform' => ReviewPlatform::Facebook,
        'source_url' => 'javascript:alert(1)',
    ]));
    HomepageRatingSummary::create(homepageTrustRatingPayload([
        'platform' => ReviewPlatform::Google,
        'publication_status' => ContentPageStatus::Draft,
        'source_url' => 'https://reviews.example.test/draft',
    ]));

    HomepageBusinessPartner::create(homepageTrustPartnerPayload($active, ['name' => 'Approved fleet partner']));
    HomepageBusinessPartner::create(homepageTrustPartnerPayload($active, [
        'name' => 'Draft partner',
        'publication_status' => ContentPageStatus::Draft,
    ]));
    HomepageBusinessPartner::create(homepageTrustPartnerPayload($inactive, ['name' => 'Inactive asset partner']));
    HomepageBusinessPartner::create(homepageTrustPartnerPayload($active, [
        'name' => 'Safe logo without unsafe link',
        'website_url' => 'javascript:alert(2)',
        'sort_order' => 30,
    ]));

    $this->get('/')
        ->assertOk()
        ->assertDontSee('data-testid="homepage-rating-summaries"', false)
        ->assertSee('data-testid="homepage-business-partners"', false)
        ->assertSee('Approved fleet partner')
        ->assertSee('Safe logo without unsafe link')
        ->assertDontSee('Draft partner')
        ->assertDontSee('Inactive asset partner')
        ->assertDontSee('javascript:alert', false);
});

test('administrator manages aggregate ratings while unsafe sources and incomplete published translations are rejected', function (): void {
    $admin = User::factory()->owner()->create();
    $customer = User::factory()->customer()->create();

    $this->get('/admin/homepage-rating-summaries')->assertRedirect('/admin/login');
    $this->actingAs($customer)->get('/admin/homepage-rating-summaries')->assertForbidden();
    $this->actingAs($admin)->get('/admin/homepage-rating-summaries/create')->assertOk()->assertSee('Omtalesammendrag');

    $this->actingAs($admin)->post('/admin/homepage-rating-summaries', homepageTrustRatingPayload([
        'source_url' => 'javascript:alert(1)',
    ]))->assertSessionHasErrors('source_url');

    $this->actingAs($admin)->post('/admin/homepage-rating-summaries', homepageTrustRatingPayload([
        'count_label_ru' => '',
    ]))->assertSessionHasErrors('count_label_ru');

    $this->actingAs($admin)->post('/admin/homepage-rating-summaries', homepageTrustRatingPayload())->assertRedirect();
    $summary = HomepageRatingSummary::query()->sole();
    $this->actingAs($admin)->put('/admin/homepage-rating-summaries/'.$summary->id, homepageTrustRatingPayload([
        'rating' => '4.9',
    ]))->assertRedirect();
    expect($summary->refresh()->rating)->toBe('4.9');
    $this->actingAs($admin)->delete('/admin/homepage-rating-summaries/'.$summary->id)->assertRedirect();
});

test('administrator can publish only an active authorized partner asset and Russian admin labels are available', function (): void {
    $admin = User::factory()->owner()->create();
    $russianAdmin = User::factory()->owner()->russian()->create();
    $active = homepageTrustAsset($admin, 'admin-active', true);
    $inactive = homepageTrustAsset($admin, 'admin-inactive', false);

    $this->actingAs($admin)->get('/admin/homepage-business-partners/create')
        ->assertOk()
        ->assertSee('Ikke last opp eller publiser tredjepartslogoer uten tillatelse');

    $this->actingAs($admin)->post('/admin/homepage-business-partners', homepageTrustPartnerPayload($inactive))
        ->assertSessionHasErrors('media_asset_id');
    $this->actingAs($admin)->post('/admin/homepage-business-partners', homepageTrustPartnerPayload($active, [
        'website_url' => 'https://user:password@example.test',
    ]))->assertSessionHasErrors('website_url');
    $this->actingAs($admin)->post('/admin/homepage-business-partners', homepageTrustPartnerPayload($active))->assertRedirect();

    $partner = HomepageBusinessPartner::query()->sole();
    expect($partner->mediaAsset?->is($active))->toBeTrue();

    $this->actingAs($russianAdmin)->get('/admin/homepage-business-partners/'.$partner->id.'/edit')
        ->assertOk()
        ->assertSee('B2B-партнёры')
        ->assertSee('без разрешения');
});

/** @param array<string, mixed> $overrides */
function homepageTrustRatingPayload(array $overrides = []): array
{
    return [...[
        'platform' => ReviewPlatform::Facebook->value,
        'rating' => '5.0',
        'review_count' => 30,
        'count_label_nb' => '30+ omtaler',
        'count_label_ru' => '30+ отзывов',
        'link_label_nb' => 'Se alle omtaler',
        'link_label_ru' => 'Все отзывы',
        'source_url' => 'https://reviews.example.test/easytire',
        'sort_order' => 10,
        'publication_status' => ContentPageStatus::Published->value,
    ], ...$overrides];
}

/** @param array<string, mixed> $overrides */
function homepageTrustPartnerPayload(MediaAsset $asset, array $overrides = []): array
{
    return [...[
        'name' => 'Fleet partner',
        'media_asset_id' => $asset->id,
        'website_url' => 'https://partner.example.test',
        'sort_order' => 10,
        'publication_status' => ContentPageStatus::Published->value,
    ], ...$overrides];
}

function homepageTrustAsset(User $uploader, string $suffix, bool $active): MediaAsset
{
    return MediaAsset::create([
        'disk' => 'public',
        'path' => "media/homepage-partner-{$suffix}.png",
        'original_name' => "partner-{$suffix}.png",
        'mime' => 'image/png',
        'byte_size' => 100,
        'width' => 180,
        'height' => 72,
        'sha256' => hash('sha256', $suffix),
        'alt_nb' => "Partner {$suffix}",
        'alt_ru' => "Партнёр {$suffix}",
        'uploader_id' => $uploader->id,
        'is_active' => $active,
    ]);
}
