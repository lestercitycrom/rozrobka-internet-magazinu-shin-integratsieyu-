<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('guests and customers cannot change product publication', function (): void {
    $productId = catalogProductId();

    $this->put('/admin/catalog/'.$productId.'/publication', ['publication_status' => 'published'])
        ->assertRedirect(route('admin.login'));
    $this->actingAs(User::factory()->customer()->create())
        ->put('/admin/catalog/'.$productId.'/publication', ['publication_status' => 'published'])
        ->assertForbidden();
});

test('an administrator can publish a reviewed product and invalid statuses are rejected', function (): void {
    $productId = catalogProductId();
    $admin = User::factory()->operations()->create();

    $this->actingAs($admin)
        ->put('/admin/catalog/'.$productId.'/publication', ['publication_status' => 'published'])
        ->assertRedirect('/admin/catalog/'.$productId);
    expect(DB::table('products')->where('id', $productId)->value('publication_status'))->toBe('published');

    $this->actingAs($admin)
        ->put('/admin/catalog/'.$productId.'/publication', ['publication_status' => 'bulk_publish'])
        ->assertSessionHasErrors('publication_status');
    expect(DB::table('products')->where('id', $productId)->value('publication_status'))->toBe('published');
});

test('publication controls are localized for Russian administrators', function (): void {
    $productId = catalogProductId();
    $admin = User::factory()->operations()->create(['locale' => 'ru']);

    $this->actingAs($admin)->get('/admin/catalog/'.$productId)
        ->assertOk()
        ->assertSee('Публикация')
        ->assertSee('Опубликован');
});

function catalogProductId(): int
{
    $brandId = DB::table('brands')->insertGetId([
        'canonical_name' => 'Publication Brand',
        'slug' => 'publication-brand',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return DB::table('products')->insertGetId([
        'product_type' => 'tire',
        'brand_id' => $brandId,
        'model_name' => 'Publication Model',
        'title' => 'Publication Product',
        'publication_status' => 'draft',
        'first_seen_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}
