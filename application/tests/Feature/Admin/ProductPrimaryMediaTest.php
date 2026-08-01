<?php

use App\Models\User;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Enums\PublicationStatus;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Product;
use App\Modules\Content\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
test('admin attaches replaces and detaches product primary media', function () {
    $admin = User::factory()->operations()->create();
    $product = mediaProduct();
    $one = mediaFor($admin);
    $two = mediaFor($admin);
    $this->actingAs($admin)->post('/admin/media/hero/product/'.$product->id, ['media_asset_id' => $one->id])->assertRedirect();
    expect($product->fresh()->mediaAssignments()->first()->media_asset_id)->toBe($one->id);
    $this->actingAs($admin)->post('/admin/media/hero/product/'.$product->id, ['media_asset_id' => $two->id])->assertRedirect();
    expect($product->fresh()->mediaAssignments()->count())->toBe(1)->and($product->fresh()->mediaAssignments()->first()->media_asset_id)->toBe($two->id);
    $this->actingAs($admin)->get('/admin/catalog/'.$product->id)->assertOk()->assertSee($two->alt_nb);
    $this->actingAs($admin)->delete('/admin/media/hero/product/'.$product->id)->assertRedirect();
    expect($product->fresh()->mediaAssignments()->count())->toBe(0);
});
test('inactive and missing media or invalid target are rejected', function () {
    $admin = User::factory()->operations()->create();
    $product = mediaProduct();
    $inactive = mediaFor($admin, ['is_active' => false]);
    $this->actingAs($admin)->post('/admin/media/hero/product/'.$product->id, ['media_asset_id' => $inactive->id])->assertSessionHasErrors('media_asset_id');
    $this->actingAs($admin)->post('/admin/media/hero/product/'.$product->id, ['media_asset_id' => 'missing'])->assertSessionHasErrors('media_asset_id');
    $this->actingAs($admin)->post('/admin/media/hero/nope/'.$product->id, ['media_asset_id' => $inactive->id])->assertNotFound();
});
test('product media section is localized and handles an empty active library', function () {
    $admin = User::factory()->operations()->create(['locale' => 'nb']);
    $product = mediaProduct();
    $this->actingAs($admin)->get('/admin/catalog/'.$product->id)->assertOk()->assertSee('Hovedbilde')->assertSee('Ingen aktive mediefiler er tilgjengelige.');
    $this->actingAs($admin->fresh()->forceFill(['locale' => 'ru']))->get('/admin/catalog/'.$product->id)->assertOk()->assertSee('Основное изображение')->assertSee('Нет доступных активных медиафайлов.');
});
test('product media selector has a localized accessible label and contains long asset names', function () {
    $admin = User::factory()->operations()->create(['locale' => 'nb']);
    $product = mediaProduct();
    $asset = mediaFor($admin, ['original_name' => str_repeat('langt-filnavn-', 20).'.png']);

    $this->actingAs($admin)->get('/admin/catalog/'.$product->id)
        ->assertOk()
        ->assertSee('for="product-primary-media"', false)
        ->assertSee('Velg mediefil')
        ->assertSee($asset->original_name)
        ->assertSee('max-w-xl', false);

    $admin->forceFill(['locale' => 'ru'])->save();
    $this->actingAs($admin)->get('/admin/catalog/'.$product->id)
        ->assertOk()
        ->assertSee('Выберите медиафайл');
});
function mediaProduct(): Product
{
    $brand = Brand::create(['canonical_name' => 'M', 'slug' => 'm']);

    return Product::create(['product_type' => ProductType::Tire, 'brand_id' => $brand->id, 'model_name' => 'M', 'title' => 'P', 'publication_status' => PublicationStatus::Draft, 'first_seen_at' => now()]);
}
function mediaFor(User $u, array $x = []): MediaAsset
{
    return MediaAsset::create([...['disk' => 'public', 'path' => 'media/'.str()->random(8).'.png', 'original_name' => 'x.png', 'mime' => 'image/png', 'byte_size' => 1, 'width' => 1, 'height' => 1, 'sha256' => str_repeat('a', 64), 'alt_nb' => 'Alt', 'uploader_id' => $u->id, 'is_active' => true], ...$x]);
}
