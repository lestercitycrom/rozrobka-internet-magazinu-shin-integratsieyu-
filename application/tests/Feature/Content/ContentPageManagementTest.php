<?php

use App\Models\User;
use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Models\ContentPage;
use Database\Seeders\ContentPageSeeder;
use Database\Seeders\E2eStorefrontSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('published information pages are public while drafts remain unavailable', function (): void {
    contentPage('om-oss', ContentPageStatus::Published);
    contentPage('kontakt', ContentPageStatus::Draft);

    $this->get('/om-oss')->assertOk()->assertSee('Om oss')->assertSee('meta-nb')->assertSee(route('storefront.content.show', 'om-oss'), false)->assertDontSee(route('storefront.content.show', 'kontakt'), false);
    $this->get('/kontakt')->assertNotFound();
});

test('russian preview page is noindex and points at the norwegian canonical url', function (): void {
    config()->set('storefront.ru_preview_enabled', true);
    contentPage('personvern', ContentPageStatus::Published);

    $this->get('/ru/personvern')
        ->assertOk()
        ->assertSee('О нас')
        ->assertSee('noindex,nofollow')
        ->assertSee('rel="canonical"', false)
        ->assertSee('/personvern"', false)
        ->assertSee(route('storefront.preview.content.show', 'personvern'), false);
});

test('checkout links only to published privacy and terms CMS pages in each locale', function (): void {
    contentPage('personvern', ContentPageStatus::Published);
    contentPage('kjopsvilkar', ContentPageStatus::Draft);

    $this->seed(E2eStorefrontSeeder::class);
    $this->withSession(['storefront.cart.lines' => ['000205550016' => 1]])
        ->get('/kasse')->assertOk()->assertSee(route('storefront.content.show', 'personvern'), false)->assertDontSee(route('storefront.content.show', 'kjopsvilkar'), false);

    config()->set('storefront.ru_preview_enabled', true);
    ContentPage::query()->where('slug', 'kjopsvilkar')->update(['publication_status' => ContentPageStatus::Published]);
    $this->withSession(['storefront.cart.lines' => ['000205550016' => 1]])
        ->get('/ru/kasse')->assertOk()->assertSee(route('storefront.preview.content.show', 'personvern'), false)->assertSee(route('storefront.preview.content.show', 'kjopsvilkar'), false);
});

test('an administrator can create update and delete localized content pages', function (): void {
    $admin = User::factory()->operations()->create();
    $payload = contentPayload('kjopsvilkar');
    $payload['publication_status'] = $payload['publication_status']->value;

    $this->actingAs($admin)->get('/admin/content-pages/create')->assertOk();
    $this->actingAs($admin)->post('/admin/content-pages', $payload)->assertRedirect();
    $page = ContentPage::query()->where('slug', 'kjopsvilkar')->firstOrFail();
    $this->actingAs($admin)->get('/admin/content-pages')->assertOk()->assertSee('Bare publiserte sider er synlige på nettstedet');
    $this->actingAs($admin)->put('/admin/content-pages/'.$page->id, [...$payload, 'title_nb' => 'Oppdatert'])->assertRedirect();
    $this->actingAs($admin)->delete('/admin/content-pages/'.$page->id)->assertRedirect();

    expect(ContentPage::query()->count())->toBe(0);
});

test('content page seed publishes complete company, contact and legal pages without overwriting editor changes', function (): void {
    $this->seed(ContentPageSeeder::class);
    expect(ContentPage::query()->count())->toBe(4)
        ->and(ContentPage::query()->whereIn('slug', ['om-oss', 'kontakt', 'personvern', 'kjopsvilkar'])->where('publication_status', 'published')->count())->toBe(4)
        ->and(ContentPage::query()->where('slug', 'kontakt')->value('body_nb'))->toContain('Ringvegen 202')
        ->and(ContentPage::query()->where('slug', 'kjopsvilkar')->value('body_nb'))->toContain('EasyTask AS');
    ContentPage::query()->where('slug', 'om-oss')->update(['title_nb' => 'Redigert']);
    $this->seed(ContentPageSeeder::class);
    expect(ContentPage::query()->where('slug', 'om-oss')->value('title_nb'))->toBe('Redigert');
});

function contentPage(string $slug, ContentPageStatus $status): ContentPage
{
    return ContentPage::create(contentPayload($slug, $status));
}

function contentPayload(string $slug, ContentPageStatus $status = ContentPageStatus::Published): array
{
    return [
        'slug' => $slug,
        'sort_order' => 1,
        'publication_status' => $status,
        'title_nb' => 'Om oss',
        'excerpt_nb' => 'Ingress',
        'body_nb' => 'Brødtekst',
        'meta_title_nb' => 'meta-nb',
        'meta_description_nb' => 'beskrivelse',
        'title_ru' => 'О нас',
        'excerpt_ru' => 'Анонс',
        'body_ru' => 'Текст',
        'meta_title_ru' => 'meta-ru',
        'meta_description_ru' => 'описание',
    ];
}
