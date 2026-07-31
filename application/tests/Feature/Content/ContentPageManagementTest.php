<?php

use App\Models\User;
use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Models\ContentPage;
use Database\Seeders\ContentPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('published information pages are public while drafts remain unavailable', function (): void {
    contentPage('om-oss', ContentPageStatus::Published);
    contentPage('kontakt', ContentPageStatus::Draft);

    $this->get('/om-oss')->assertOk()->assertSee('Om oss')->assertSee('meta-nb');
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
        ->assertDontSee('/ru/personvern', false);
});

test('an administrator can create update and delete localized content pages', function (): void {
    $admin = User::factory()->operations()->create();
    $payload = contentPayload('kjopsvilkar');
    $payload['publication_status'] = $payload['publication_status']->value;

    $this->actingAs($admin)->post('/admin/content-pages', $payload)->assertRedirect();
    $page = ContentPage::query()->where('slug', 'kjopsvilkar')->firstOrFail();
    $this->actingAs($admin)->put('/admin/content-pages/'.$page->id, [...$payload, 'title_nb' => 'Oppdatert'])->assertRedirect();
    $this->actingAs($admin)->delete('/admin/content-pages/'.$page->id)->assertRedirect();

    expect(ContentPage::query()->count())->toBe(0);
});

test('content page seed is non-destructive and leaves legal and contact pages in review draft state', function (): void {
    $this->seed(ContentPageSeeder::class);
    expect(ContentPage::query()->count())->toBe(4)
        ->and(ContentPage::query()->whereIn('slug', ['kontakt', 'personvern', 'kjopsvilkar'])->where('publication_status', 'draft')->count())->toBe(3);
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
