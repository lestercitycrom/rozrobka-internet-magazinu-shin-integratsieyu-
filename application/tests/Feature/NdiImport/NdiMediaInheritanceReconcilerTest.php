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
use App\Modules\NdiImport\NdiMediaInheritanceReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('it reuses a downloaded NDI image for the exact same make and model group', function (): void {
    $supplier = inheritanceSupplier();
    $brand = inheritanceBrand('Exact Brand');
    $donor = inheritanceProduct($supplier, $brand, 'Exact Pattern', ProductType::Tire, 'DONOR-1');
    $target = inheritanceProduct($supplier, $brand, ' exact   pattern ', ProductType::Tire, 'TARGET-1');
    $asset = inheritanceAsset('a');
    $source = inheritanceExplicitSource($supplier, $donor, inheritanceUrl('11111111-1111-4111-8111-111111111111'), $asset);

    $summary = app(NdiMediaInheritanceReconciler::class)->reconcile($supplier);
    $inherited = ProductMediaSource::query()->where('product_id', $target->id)->firstOrFail();

    expect($summary)->toBe(['created' => 1, 'updated' => 0, 'removed' => 0])
        ->and($inherited->source_kind)->toBe(NdiMediaInheritanceReconciler::INHERITED_SOURCE_KIND)
        ->and($inherited->source_url)->toBe($source->source_url)
        ->and($inherited->media_asset_id)->toBe($asset->id)
        ->and($inherited->status)->toBe('downloaded')
        ->and(MediaAssignment::query()->where('assignable_id', $target->id)->where('role', 'primary')->value('media_asset_id'))->toBe($asset->id);
});

test('it never inherits across manufacturer model or product type boundaries', function (): void {
    $supplier = inheritanceSupplier();
    $brand = inheritanceBrand('Boundary Brand');
    $otherBrand = inheritanceBrand('Other Brand');
    $donor = inheritanceProduct($supplier, $brand, 'Pattern One', ProductType::Tire, 'DONOR-2');
    $differentBrand = inheritanceProduct($supplier, $otherBrand, 'Pattern One', ProductType::Tire, 'TARGET-2A');
    $differentModel = inheritanceProduct($supplier, $brand, 'Pattern Two', ProductType::Tire, 'TARGET-2B');
    $differentType = inheritanceProduct($supplier, $brand, 'Pattern One', ProductType::Wheel, 'TARGET-2C');
    $emptyDonor = inheritanceProduct($supplier, $brand, '', ProductType::Tire, 'DONOR-2D');
    $emptyTarget = inheritanceProduct($supplier, $brand, '', ProductType::Tire, 'TARGET-2D');
    inheritanceExplicitSource($supplier, $donor, inheritanceUrl('22222222-2222-4222-8222-222222222222'), inheritanceAsset('b'));
    inheritanceExplicitSource($supplier, $emptyDonor, inheritanceUrl('88888888-8888-4888-8888-888888888888'), inheritanceAsset('8'));

    expect(app(NdiMediaInheritanceReconciler::class)->reconcile($supplier))
        ->toBe(['created' => 0, 'updated' => 0, 'removed' => 0])
        ->and(ProductMediaSource::query()->whereIn('product_id', [$differentBrand->id, $differentModel->id, $differentType->id, $emptyTarget->id])->exists())
        ->toBeFalse();
});

test('it removes inherited media when an exact group contains conflicting official urls', function (): void {
    $supplier = inheritanceSupplier();
    $brand = inheritanceBrand('Conflict Brand');
    $first = inheritanceProduct($supplier, $brand, 'Conflict Pattern', ProductType::Tire, 'DONOR-3A');
    $second = inheritanceProduct($supplier, $brand, 'Conflict Pattern', ProductType::Tire, 'DONOR-3B');
    $target = inheritanceProduct($supplier, $brand, 'Conflict Pattern', ProductType::Tire, 'TARGET-3');
    $oldAsset = inheritanceAsset('c');
    inheritanceExplicitSource($supplier, $first, inheritanceUrl('33333333-3333-4333-8333-333333333333'), inheritanceAsset('d'));
    inheritanceExplicitSource($supplier, $second, inheritanceUrl('44444444-4444-4444-8444-444444444444'), inheritanceAsset('e'));
    $inherited = ProductMediaSource::query()->create([
        'product_id' => $target->id,
        'source_kind' => NdiMediaInheritanceReconciler::INHERITED_SOURCE_KIND,
        'source_url' => inheritanceUrl('55555555-5555-4555-8555-555555555555'),
        'source_artifact_id' => inheritanceArtifact($supplier),
        'media_asset_id' => $oldAsset->id,
        'status' => 'downloaded',
        'downloaded_at' => now(),
    ]);
    MediaAssignment::query()->create([
        'media_asset_id' => $oldAsset->id,
        'assignable_type' => Product::class,
        'assignable_id' => $target->id,
        'role' => 'primary',
    ]);

    expect(app(NdiMediaInheritanceReconciler::class)->reconcile($supplier))
        ->toBe(['created' => 0, 'updated' => 0, 'removed' => 1])
        ->and($inherited->fresh())->toBeNull()
        ->and(MediaAssignment::query()->where('assignable_id', $target->id)->exists())->toBeFalse();
});

test('it records inherited provenance without replacing an administrator primary image', function (): void {
    $supplier = inheritanceSupplier();
    $brand = inheritanceBrand('Override Brand');
    $donor = inheritanceProduct($supplier, $brand, 'Override Pattern', ProductType::Tire, 'DONOR-4');
    $target = inheritanceProduct($supplier, $brand, 'Override Pattern', ProductType::Tire, 'TARGET-4');
    $supplierAsset = inheritanceAsset('f');
    $manualAsset = inheritanceAsset('9', uploaderId: 1);
    inheritanceExplicitSource($supplier, $donor, inheritanceUrl('66666666-6666-4666-8666-666666666666'), $supplierAsset);
    MediaAssignment::query()->create([
        'media_asset_id' => $manualAsset->id,
        'assignable_type' => Product::class,
        'assignable_id' => $target->id,
        'role' => 'primary',
    ]);

    app(NdiMediaInheritanceReconciler::class)->reconcile($supplier);

    expect(ProductMediaSource::query()->where('product_id', $target->id)->value('media_asset_id'))->toBe($supplierAsset->id)
        ->and(MediaAssignment::query()->where('assignable_id', $target->id)->value('media_asset_id'))->toBe($manualAsset->id);
});

function inheritanceSupplier(): Supplier
{
    return Supplier::query()->create(['code' => (string) config('ndi.supplier_code'), 'name' => 'NDI', 'is_active' => true]);
}

function inheritanceBrand(string $name): Brand
{
    return Brand::query()->create(['canonical_name' => $name, 'slug' => Str::slug($name)]);
}

function inheritanceProduct(Supplier $supplier, Brand $brand, string $model, ProductType $type, string $sku): Product
{
    $product = Product::query()->create([
        'product_type' => $type,
        'brand_id' => $brand->id,
        'model_name' => $model,
        'title' => $sku,
        'publication_status' => PublicationStatus::Draft,
        'first_seen_at' => now(),
        'last_catalog_seen_at' => now(),
    ]);
    SupplierSku::query()->create([
        'supplier_id' => $supplier->id,
        'product_id' => $product->id,
        'external_sku' => $sku,
        'catalog_state' => CatalogState::Catalog,
    ]);

    return $product;
}

function inheritanceExplicitSource(Supplier $supplier, Product $product, string $url, MediaAsset $asset): ProductMediaSource
{
    return ProductMediaSource::query()->create([
        'product_id' => $product->id,
        'source_kind' => NdiMediaInheritanceReconciler::EXPLICIT_SOURCE_KIND,
        'source_url' => $url,
        'source_artifact_id' => inheritanceArtifact($supplier),
        'media_asset_id' => $asset->id,
        'status' => 'downloaded',
        'last_attempted_at' => now(),
        'downloaded_at' => now(),
    ]);
}

function inheritanceArtifact(Supplier $supplier): string
{
    $id = (string) Str::ulid();
    DB::table('source_artifacts')->insert([
        'id' => $id,
        'supplier_id' => $supplier->id,
        'kind' => 'fixture',
        'original_filename' => $id.'.xlsx',
        'storage_key' => 'fixtures/'.$id.'.xlsx',
        'byte_size' => 1,
        'sha256' => hash('sha256', $id),
        'received_at' => now(),
        'is_redacted' => true,
    ]);

    return $id;
}

function inheritanceAsset(string $seed, ?int $uploaderId = null): MediaAsset
{
    if ($uploaderId !== null) {
        $uploaderId = User::factory()->create()->id;
    }

    return MediaAsset::query()->create([
        'disk' => 'public',
        'path' => 'media/'.$seed.'.png',
        'original_name' => $seed.'.png',
        'mime' => 'image/png',
        'byte_size' => 1,
        'width' => 1,
        'height' => 1,
        'sha256' => str_repeat($seed, 64),
        'uploader_id' => $uploaderId,
        'is_active' => true,
    ]);
}

function inheritanceUrl(string $uuid): string
{
    return 'https://files.ndias.com/Imagev2.aspx?id='.$uuid;
}
