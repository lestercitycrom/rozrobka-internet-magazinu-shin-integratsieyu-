<?php

use App\Models\User;
use App\Modules\Content\Models\ServicePage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an administrator can create and edit localized service pages', function (): void {
    $admin = User::factory()->owner()->create();
    $payload = ['slug' => 'dekkskift', 'sort_order' => 3, 'publication_status' => 'draft', 'request_flow' => 'seasonal', 'title_nb' => 'Dekkskift', 'excerpt_nb' => 'Norsk ingress', 'body_nb' => 'Norsk tekst', 'seo_title_nb' => 'SEO NO', 'seo_description_nb' => 'Beskrivelse', 'title_ru' => 'Замена шин', 'excerpt_ru' => 'Русский анонс', 'body_ru' => 'Русский текст', 'seo_title_ru' => 'SEO RU', 'seo_description_ru' => 'Описание'];

    $this->actingAs($admin)->get('/admin/services')->assertOk()->assertSee('Tjenestesider')->assertSee('Ny tjenesteside');
    $this->actingAs($admin)->get('/admin/services/create')->assertOk()
        ->assertSee('Russisk er en testoversettelse')
        ->assertSee('rounded-md border border-easytire-border', false)
        ->assertSee('resize-y rounded-md border', false);
    $this->actingAs($admin)->post('/admin/services', $payload)->assertRedirect();
    $this->assertDatabaseHas('service_pages', ['slug' => 'dekkskift', 'title_ru' => 'Замена шин', 'publication_status' => 'draft', 'request_flow' => 'seasonal']);
    $page = ServicePage::firstOrFail();
    $this->actingAs($admin)->put('/admin/services/'.$page->id, [...$payload, 'title_nb' => 'Oppdatert', 'publication_status' => 'published'])->assertRedirect();
    $this->assertDatabaseHas('service_pages', ['id' => $page->id, 'title_nb' => 'Oppdatert', 'publication_status' => 'published']);
    $this->actingAs($admin)->get('/admin/services')->assertOk()->assertSee('Oppdatert')->assertSee('Publisert');
});

test('service page management validates input and renders russian labels', function (): void {
    $admin = User::factory()->owner()->create(['locale' => 'ru']);
    $this->actingAs($admin)->post('/admin/services', [])->assertSessionHasErrors(['slug', 'request_flow', 'title_nb', 'title_ru']);
    $this->actingAs($admin)->get('/admin/services/create')->assertOk()->assertSee('Русский язык — тестовый перевод');
});

test('service page slugs must be unique', function (): void {
    $admin = User::factory()->owner()->create();
    ServicePage::create(['slug' => 'unique-page', 'sort_order' => 1, 'publication_status' => 'draft', 'title_nb' => 'NO', 'title_ru' => 'RU']);

    $this->actingAs($admin)->post('/admin/services', ['slug' => 'unique-page', 'sort_order' => 2, 'publication_status' => 'draft', 'request_flow' => 'generic', 'title_nb' => 'Another', 'title_ru' => 'Другой'])
        ->assertSessionHasErrors('slug');
});

test('service page canonical paths cannot collide with fixed storefront routes', function (): void {
    $admin = User::factory()->owner()->create();

    $this->actingAs($admin)->post('/admin/services', [
        'slug' => 'campaign-page',
        'canonical_path' => 'handlekurv',
        'sort_order' => 2,
        'publication_status' => 'draft',
        'request_flow' => 'generic',
        'title_nb' => 'Kampanje',
        'title_ru' => 'Кампания',
    ])->assertSessionHasErrors('canonical_path');

    $this->assertDatabaseMissing('service_pages', ['slug' => 'campaign-page']);
});

test('guests and customers cannot manage service pages', function (): void {
    $this->get('/admin/services')->assertRedirect(route('admin.login'));
    $this->actingAs(User::factory()->customer()->create())->get('/admin/services')->assertForbidden();
});
