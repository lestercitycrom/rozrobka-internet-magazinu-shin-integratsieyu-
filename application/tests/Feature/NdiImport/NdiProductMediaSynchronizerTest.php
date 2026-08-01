<?php

use App\Models\User;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Enums\PublicationStatus;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductMediaSource;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Content\Models\MediaAsset;
use App\Modules\Content\Models\MediaAssignment;
use App\Modules\NdiImport\NdiMediaUrl;
use App\Modules\NdiImport\NdiCatalogPublisher;
use App\Modules\NdiImport\NdiProductMediaSynchronizer;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('ndi.media_hosts', ['files.ndias.com']);
});

test('it only accepts the documented NDI image endpoint and upgrades it to HTTPS', function (): void {
    expect(NdiMediaUrl::canonicalize('http://files.ndias.com/Imagev2.aspx?id=4F0B9D8C-B171-49CB-9EE8-B46FE79E4661'))
        ->toBe('https://files.ndias.com/Imagev2.aspx?id=4f0b9d8c-b171-49cb-9ee8-b46fe79e4661')
        ->and(NdiMediaUrl::canonicalize('https://example.invalid/image.png?id=4f0b9d8c-b171-49cb-9ee8-b46fe79e4661'))
        ->toBeNull()
        ->and(NdiMediaUrl::canonicalize('https://files.ndias.com/Imagev2.aspx?id=not-a-uuid'))
        ->toBeNull();
});

test('it downloads one NDI binary once and assigns it to every product carrying the same NDI source id', function (): void {
    $url = ndiMediaUrl();
    $first = ndiMediaSource(ndiMediaProduct(), $url);
    $second = ndiMediaSource(ndiMediaProduct(), $url);
    Http::fake([$url => Http::response(ndiPngBytes(), 200, ['Content-Type' => 'image/png'])]);

    $summary = app(NdiProductMediaSynchronizer::class)->sync();

    expect($summary)->toBe(['downloaded' => 1, 'failed' => 0, 'assigned' => 2])
        ->and(MediaAsset::query()->whereNull('uploader_id')->count())->toBe(1)
        ->and($first->fresh()->status)->toBe('downloaded')
        ->and($second->fresh()->status)->toBe('downloaded')
        ->and($first->fresh()->media_asset_id)->toBe($second->fresh()->media_asset_id)
        ->and(MediaAssignment::query()->where('role', 'primary')->count())->toBe(2);

    Storage::disk('public')->assertExists(MediaAsset::firstOrFail()->path);
});

test('it records a failed supplier response without exposing or assigning a remote URL', function (): void {
    $source = ndiMediaSource(ndiMediaProduct(), ndiMediaUrl());
    Http::fake(['https://files.ndias.com/*' => Http::response('', 302, ['Location' => 'https://example.invalid'])]);

    $summary = app(NdiProductMediaSynchronizer::class)->sync();

    expect($summary)->toBe(['downloaded' => 0, 'failed' => 1, 'assigned' => 0])
        ->and($source->fresh()->status)->toBe('failed')
        ->and($source->fresh()->media_asset_id)->toBeNull()
        ->and(MediaAsset::query()->count())->toBe(0)
        ->and(MediaAssignment::query()->count())->toBe(0);
});

test('it rejects an oversized response from its headers before accepting supplier media', function (): void {
    $method = new ReflectionMethod(NdiProductMediaSynchronizer::class, 'downloadOptions');
    $options = $method->invoke(app(NdiProductMediaSynchronizer::class), 32);

    expect(fn () => $options['on_headers'](new Response(200, ['Content-Length' => '33'])))
        ->toThrow(RuntimeException::class, 'exceeds the allowed download size')
        ->and(fn () => $options['progress'](0, 33))
        ->toThrow(RuntimeException::class, 'exceeded the allowed download size');
});

test('it respects retry backoff and does not request failed source media every hour', function (): void {
    $source = ndiMediaSource(ndiMediaProduct(), ndiMediaUrl());
    $source->update(['status' => 'failed', 'attempt_count' => 1, 'last_attempted_at' => now()]);
    Http::fake();

    expect(app(NdiProductMediaSynchronizer::class)->sync())->toBe(['downloaded' => 0, 'failed' => 0, 'assigned' => 0]);
    Http::assertNothingSent();
});

test('it replaces a corrupt local destination only after validated NDI bytes are available', function (): void {
    $bytes = ndiPngBytes();
    $sha256 = hash('sha256', $bytes);
    Storage::disk('public')->put('media/ndi/'.$sha256.'.png', 'corrupt');
    ndiMediaSource(ndiMediaProduct(), ndiMediaUrl());
    Http::fake(['https://files.ndias.com/*' => Http::response($bytes, 200, ['Content-Type' => 'image/png'])]);

    app(NdiProductMediaSynchronizer::class)->sync();

    expect(Storage::disk('public')->get('media/ndi/'.$sha256.'.png'))->toBe($bytes);
});

test('it repairs an existing supplier asset whose local file is missing', function (): void {
    $bytes = ndiPngBytes();
    $sha256 = hash('sha256', $bytes);
    $path = 'media/ndi/'.$sha256.'.png';
    $asset = MediaAsset::create([
        'disk' => 'public', 'path' => $path, 'original_name' => 'ndi-'.$sha256.'.png', 'mime' => 'image/png',
        'byte_size' => strlen($bytes), 'width' => 24, 'height' => 16, 'sha256' => $sha256,
        'uploader_id' => null, 'is_active' => true,
    ]);
    $source = ndiMediaSource(ndiMediaProduct(), ndiMediaUrl());
    Http::fake(['https://files.ndias.com/*' => Http::response($bytes, 200, ['Content-Type' => 'image/png'])]);

    app(NdiProductMediaSynchronizer::class)->sync();

    expect($source->fresh()->media_asset_id)->toBe($asset->id)
        ->and(Storage::disk('public')->get($path))->toBe($bytes);
});

test('removing an NDI image source removes only its own primary assignment', function (): void {
    $supplierAsset = MediaAsset::create([
        'disk' => 'public', 'path' => 'media/ndi/supplier.png', 'original_name' => 'supplier.png', 'mime' => 'image/png',
        'byte_size' => 1, 'width' => 1, 'height' => 1, 'sha256' => str_repeat('c', 64),
        'uploader_id' => null, 'is_active' => true,
    ]);
    $manualAsset = MediaAsset::create([
        'disk' => 'public', 'path' => 'media/manual.png', 'original_name' => 'manual.png', 'mime' => 'image/png',
        'byte_size' => 1, 'width' => 1, 'height' => 1, 'sha256' => str_repeat('d', 64),
        'uploader_id' => null, 'is_active' => true,
    ]);
    $supplierProduct = ndiMediaProduct();
    $manualProduct = ndiMediaProduct();
    $supplierSource = ndiMediaSource($supplierProduct, ndiMediaUrl());
    $manualSource = ndiMediaSource($manualProduct, ndiMediaUrl());
    $supplierSource->update(['media_asset_id' => $supplierAsset->id, 'status' => 'downloaded']);
    $manualSource->update(['media_asset_id' => $supplierAsset->id, 'status' => 'downloaded']);
    MediaAssignment::create(['media_asset_id' => $supplierAsset->id, 'assignable_type' => Product::class, 'assignable_id' => $supplierProduct->id, 'role' => 'primary']);
    MediaAssignment::create(['media_asset_id' => $manualAsset->id, 'assignable_type' => Product::class, 'assignable_id' => $manualProduct->id, 'role' => 'primary']);

    $method = new ReflectionMethod(NdiCatalogPublisher::class, 'publishMediaSource');
    $publisher = app(NdiCatalogPublisher::class);
    $method->invoke($publisher, $supplierProduct, null, $supplierSource->source_artifact_id);
    $method->invoke($publisher, $manualProduct, null, $manualSource->source_artifact_id);

    expect(ProductMediaSource::query()->whereIn('product_id', [$supplierProduct->id, $manualProduct->id])->count())->toBe(0)
        ->and(MediaAssignment::query()->where('assignable_id', $supplierProduct->id)->exists())->toBeFalse()
        ->and(MediaAssignment::query()->where('assignable_id', $manualProduct->id)->firstOrFail()->media_asset_id)->toBe($manualAsset->id);
});

test('an existing primary image remains the storefront primary regardless of uploader provenance', function (): void {
    $product = ndiMediaProduct();
    $manual = MediaAsset::create([
        'disk' => 'public', 'path' => 'media/manual.png', 'original_name' => 'manual.png', 'mime' => 'image/png',
        'byte_size' => 1, 'width' => 1, 'height' => 1, 'sha256' => str_repeat('a', 64),
        'uploader_id' => null, 'is_active' => true,
    ]);
    MediaAssignment::create(['media_asset_id' => $manual->id, 'assignable_type' => Product::class, 'assignable_id' => $product->id, 'role' => 'primary']);
    $source = ndiMediaSource($product, ndiMediaUrl());
    Http::fake(['https://files.ndias.com/*' => Http::response(ndiPngBytes(), 200, ['Content-Type' => 'image/png'])]);

    app(NdiProductMediaSynchronizer::class)->sync();

    expect($source->fresh()->status)->toBe('downloaded')
        ->and($product->fresh()->mediaAssignments()->where('role', 'primary')->firstOrFail()->media_asset_id)->toBe($manual->id);
});

test('admin cannot delete a supplier asset retained by media provenance', function (): void {
    $admin = User::factory()->operations()->create();
    $asset = MediaAsset::create([
        'disk' => 'public', 'path' => 'media/ndi/protected.png', 'original_name' => 'protected.png', 'mime' => 'image/png',
        'byte_size' => 1, 'width' => 1, 'height' => 1, 'sha256' => str_repeat('e', 64),
        'uploader_id' => null, 'is_active' => true,
    ]);
    $source = ndiMediaSource(ndiMediaProduct(), ndiMediaUrl());
    $source->update(['media_asset_id' => $asset->id, 'status' => 'downloaded']);

    $this->actingAs($admin)->delete('/admin/media/'.$asset->id)->assertStatus(409);
    expect($asset->fresh())->not->toBeNull()->and($source->fresh()->media_asset_id)->toBe($asset->id);
});

function ndiMediaUrl(): string
{
    return 'https://files.ndias.com/Imagev2.aspx?id=4f0b9d8c-b171-49cb-9ee8-b46fe79e4661';
}

function ndiPngBytes(): string
{
    $file = UploadedFile::fake()->image('ndi.png', 24, 16);

    return (string) file_get_contents($file->getRealPath());
}

function ndiMediaProduct(): Product
{
    $brand = Brand::query()->firstOrCreate(['canonical_name' => 'NDI media fixture'], ['slug' => 'ndi-media-fixture']);

    return Product::create([
        'product_type' => ProductType::Tire,
        'brand_id' => $brand->id,
        'model_name' => 'Fixture',
        'title' => 'NDI media fixture',
        'publication_status' => PublicationStatus::Draft,
        'first_seen_at' => now(),
    ]);
}

function ndiMediaSource(Product $product, string $url): ProductMediaSource
{
    $supplier = Supplier::query()->firstOrCreate(['code' => 'ndi-media-fixture'], ['name' => 'NDI fixture', 'is_active' => true]);
    $artifactId = (string) Str::ulid();
    DB::table('source_artifacts')->insert([
        'id' => $artifactId, 'supplier_id' => $supplier->id, 'kind' => 'fixture', 'original_filename' => 'media.xlsx',
        'storage_key' => 'fixtures/media-'.$artifactId.'.xlsx', 'byte_size' => 1, 'sha256' => hash('sha256', $artifactId),
        'received_at' => now(), 'is_redacted' => true,
    ]);

    return ProductMediaSource::create(['product_id' => $product->id, 'source_url' => $url, 'source_artifact_id' => $artifactId]);
}
