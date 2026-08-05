<?php

use App\Models\User;
use App\Modules\Catalog\Enums\CatalogState;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Enums\PublicationStatus;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductMediaSource;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\SupplierSku;
use App\Modules\Content\Models\MediaAsset;
use App\Modules\Content\Models\MediaAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
test('detaching supplier media records an explicit suppression for reconciliation', function () {
    $admin = User::factory()->operations()->create();
    $product = mediaProduct();
    $asset = mediaFor($admin);
    $supplierId = SupplierSku::query()->where('product_id', $product->id)->value('supplier_id');
    $artifactId = (string) Str::ulid();
    DB::table('source_artifacts')->insert([
        'id' => $artifactId, 'supplier_id' => $supplierId, 'kind' => 'fixture', 'original_filename' => 'media.xlsx',
        'storage_key' => 'fixtures/'.$artifactId.'.xlsx', 'byte_size' => 1, 'sha256' => hash('sha256', $artifactId),
        'received_at' => now(), 'is_redacted' => true,
    ]);
    $source = ProductMediaSource::create([
        'product_id' => $product->id, 'source_url' => 'https://files.ndias.com/Imagev2.aspx?id=11111111-1111-4111-8111-111111111111',
        'source_artifact_id' => $artifactId, 'media_asset_id' => $asset->id, 'status' => 'downloaded',
    ]);
    MediaAssignment::create(['media_asset_id' => $asset->id, 'assignable_type' => Product::class, 'assignable_id' => $product->id, 'role' => 'primary']);

    $this->actingAs($admin)->delete('/admin/media/hero/product/'.$product->id)->assertRedirect();

    expect($product->fresh()->mediaAssignments()->count())->toBe(0)
        ->and($source->fresh()->is_suppressed)->toBeTrue();

    $this->actingAs($admin)->post('/admin/media/hero/product/'.$product->id, ['media_asset_id' => $asset->id])->assertRedirect();

    expect($source->fresh()->is_suppressed)->toBeFalse();
});
test('product media section is localized and handles an empty active library', function () {
    $admin = User::factory()->operations()->create(['locale' => 'nb']);
    $product = mediaProduct();
    $this->actingAs($admin)->get('/admin/catalog/'.$product->id)->assertOk()->assertSee('Hovedbilde')->assertSee('Ingen aktive mediefiler er tilgjengelige.');
    $this->actingAs($admin->fresh()->forceFill(['locale' => 'ru']))->get('/admin/catalog/'.$product->id)->assertOk()->assertSee('Основное изображение')->assertSee('Нет доступных активных медиафайлов.');
});
test('product media chooser shows real previews and factual product context instead of generic file names', function () {
    $admin = User::factory()->operations()->create(['locale' => 'nb']);
    $product = mediaProduct();
    $asset = mediaFor($admin, [
        'original_name' => str_repeat('hash-', 20).'.png',
        'alt_nb' => 'Produktbilde',
        'alt_ru' => 'Изображение товара',
    ]);
    $this->actingAs($admin)->post('/admin/media/hero/product/'.$product->id, ['media_asset_id' => $asset->id])->assertRedirect();
    $otherProduct = Product::create([
        'product_type' => ProductType::Tire,
        'brand_id' => $product->brand_id,
        'model_name' => 'M',
        'title' => 'P2',
        'publication_status' => PublicationStatus::Draft,
        'first_seen_at' => now(),
    ]);
    MediaAssignment::create(['media_asset_id' => $asset->id, 'assignable_type' => Product::class, 'assignable_id' => $otherProduct->id, 'role' => 'primary']);

    $this->actingAs($admin)->get('/admin/catalog/'.$product->id)
        ->assertOk()
        ->assertSee('data-testid="current-product-media-preview"', false)
        ->assertSee('data-testid="product-media-chooser"', false)
        ->assertSee('Velg mediefil')
        ->assertSee('type="radio"', false)
        ->assertSee('value="'.$asset->id.'"', false)
        ->assertSee('SKU MEDIA-SKU')
        ->assertSee('2 tilknyttede produkter')
        ->assertSee(Storage::disk('public')->url($asset->path), false)
        ->assertDontSee($asset->original_name);

    $admin->forceFill(['locale' => 'ru'])->save();
    $this->actingAs($admin)->get('/admin/catalog/'.$product->id)
        ->assertOk()
        ->assertSee('Выберите медиафайл')
        ->assertSee('Связано товаров: 2')
        ->assertSee('Изображение товара');
});
function mediaProduct(): Product
{
    $brand = Brand::create(['canonical_name' => 'M', 'slug' => 'm']);
    $supplier = Supplier::create(['code' => 'media', 'name' => 'Media supplier', 'is_active' => true]);
    $product = Product::create(['product_type' => ProductType::Tire, 'brand_id' => $brand->id, 'model_name' => 'M', 'title' => 'P', 'publication_status' => PublicationStatus::Draft, 'first_seen_at' => now()]);
    SupplierSku::create(['supplier_id' => $supplier->id, 'product_id' => $product->id, 'external_sku' => 'MEDIA-SKU', 'catalog_state' => CatalogState::Catalog]);

    return $product;
}
function mediaFor(User $u, array $x = []): MediaAsset
{
    return MediaAsset::create([...['disk' => 'public', 'path' => 'media/'.str()->random(8).'.png', 'original_name' => 'x.png', 'mime' => 'image/png', 'byte_size' => 1, 'width' => 1, 'height' => 1, 'sha256' => str_repeat('a', 64), 'alt_nb' => 'Alt', 'uploader_id' => $u->id, 'is_active' => true], ...$x]);
}
