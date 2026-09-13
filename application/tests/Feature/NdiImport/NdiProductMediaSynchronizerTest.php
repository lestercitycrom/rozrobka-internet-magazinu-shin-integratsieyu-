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
use App\Modules\NdiImport\NdiCatalogPublisher;
use App\Modules\NdiImport\NdiMediaUrl;
use App\Modules\NdiImport\NdiProductMediaSynchronizer;
use GuzzleHttp\Psr7\Response;
use Illuminate\Console\Scheduling\Schedule;
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
    config()->set('ndi.media_sync_enabled', true);
});

test('supplier media synchronization returns an explicit disabled result', function (): void {
    config()->set('ndi.media_sync_enabled', false);

    expect(config('ndi.media_sync_enabled'))->toBeFalse();
});

test('disabled direct synchronization does not mutate a downloaded source due for refresh', function (bool $force): void {
    config()->set('ndi.media_sync_enabled', false);

    $bytes = ndiPngBytes(20, 20);
    $sha256 = hash('sha256', $bytes);
    $path = 'media/ndi/'.$sha256.'.png';
    Storage::disk('public')->put($path, $bytes);
    $asset = MediaAsset::create([
        'disk' => 'public', 'path' => $path, 'original_name' => 'existing.png', 'mime' => 'image/png',
        'byte_size' => strlen($bytes), 'width' => 20, 'height' => 20, 'sha256' => $sha256,
        'uploader_id' => null, 'is_active' => true,
    ]);
    $source = ndiMediaSource(ndiMediaProduct(), ndiMediaUrl());
    $source->update([
        'media_asset_id' => $asset->id,
        'status' => 'downloaded',
        'attempt_count' => 3,
        'downloaded_at' => now()->subDays(8),
    ]);
    $assignment = MediaAssignment::create([
        'media_asset_id' => $asset->id,
        'assignable_type' => Product::class,
        'assignable_id' => $source->product_id,
        'role' => 'primary',
    ]);
    $sourceBefore = $source->fresh()->getAttributes();
    $assetBefore = $asset->fresh()->getAttributes();
    $assignmentBefore = $assignment->fresh()->getAttributes();
    $filesBefore = Storage::disk('public')->allFiles();
    Http::fake();

    $summary = app(NdiProductMediaSynchronizer::class)->sync(25, $force);

    expect($summary)->toBe(['downloaded' => 0, 'failed' => 0, 'assigned' => 0, 'disabled' => true])
        ->and($source->fresh()->getAttributes())->toBe($sourceBefore)
        ->and($asset->fresh()->getAttributes())->toBe($assetBefore)
        ->and($assignment->fresh()->getAttributes())->toBe($assignmentBefore)
        ->and(Storage::disk('public')->allFiles())->toBe($filesBefore)
        ->and(Storage::disk('public')->get($path))->toBe($bytes);
    Http::assertNothingSent();
})->with([false, true]);

test('disabled force command reports an honest no-op without supplier mutations', function (): void {
    config()->set('ndi.media_sync_enabled', false);
    $source = ndiMediaSource(ndiMediaProduct(), ndiMediaUrl());
    $sourceBefore = $source->fresh()->getAttributes();
    Http::fake();

    $this->artisan('ndi:media:sync', ['--force' => true])
        ->expectsOutputToContain('NDI product media synchronization is disabled.')
        ->assertSuccessful();

    expect($source->fresh()->getAttributes())->toBe($sourceBefore)
        ->and(MediaAsset::query()->count())->toBe(0)
        ->and(MediaAssignment::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
    Http::assertNothingSent();
});

test('disabled supplier media event is rejected by the scheduler condition', function (): void {
    config()->set('ndi.media_sync_enabled', false);

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'ndi:media:sync'));

    expect($event)->not->toBeNull()
        ->and($event->filtersPass(app()))->toBeFalse();
});

test('disabled scheduled synchronization leaves supplier media state untouched', function (): void {
    config()->set('ndi.media_sync_enabled', false);
    $source = ndiMediaSource(ndiMediaProduct(), ndiMediaUrl());
    $sourceBefore = $source->fresh()->getAttributes();
    Http::fake();
    $this->travelTo(now()->startOfHour());

    $this->artisan('schedule:run')->assertSuccessful();

    expect($source->fresh()->getAttributes())->toBe($sourceBefore)
        ->and(MediaAsset::query()->count())->toBe(0)
        ->and(MediaAssignment::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
    Http::assertNothingSent();
});

test('supplier media event is admitted only after explicit enablement', function (): void {
    config()->set('ndi.media_sync_enabled', true);

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'ndi:media:sync'));

    expect($event)->not->toBeNull()
        ->and($event->filtersPass(app()))->toBeTrue();
});

test('it only accepts the documented NDI image endpoint and upgrades it to HTTPS', function (): void {
    expect(NdiMediaUrl::canonicalize('http://files.ndias.com/Imagev2.aspx?id=4F0B9D8C-B171-49CB-9EE8-B46FE79E4661'))
        ->toBe('https://files.ndias.com/Imagev2.aspx?id=4f0b9d8c-b171-49cb-9ee8-b46fe79e4661')
        ->and(NdiMediaUrl::canonicalize('https://example.invalid/image.png?id=4f0b9d8c-b171-49cb-9ee8-b46fe79e4661'))
        ->toBeNull()
        ->and(NdiMediaUrl::canonicalize('https://files.ndias.com/Imagev2.aspx?id=not-a-uuid'))
        ->toBeNull()
        ->and(NdiMediaUrl::isExplicitlyEmpty('http://files.ndias.com/Imagev2.aspx?id='))
        ->toBeTrue()
        ->and(NdiMediaUrl::isExplicitlyEmpty('https://files.ndias.com/Imagev3.aspx?id='))
        ->toBeFalse()
        ->and(NdiMediaUrl::isExplicitlyEmpty('https://files.ndias.com/Imagev2.aspx?id=#fragment'))
        ->toBeFalse()
        ->and(NdiMediaUrl::isExplicitlyEmpty('https://user@files.ndias.com/Imagev2.aspx?id='))
        ->toBeFalse()
        ->and(NdiMediaUrl::isExplicitlyEmpty('https://user:pass@files.ndias.com/Imagev2.aspx?id='))
        ->toBeFalse()
        ->and(NdiMediaUrl::isExplicitlyEmpty('https://files.ndias.com/Imagev2.aspx?id=&extra=1'))
        ->toBeFalse()
        ->and(NdiMediaUrl::canonicalize('https://user@files.ndias.com/Imagev2.aspx?id=4f0b9d8c-b171-49cb-9ee8-b46fe79e4661'))
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

test('it keeps retrying an available source after the former maximum attempt count', function (): void {
    $source = ndiMediaSource(ndiMediaProduct(), ndiMediaUrl());
    $source->update(['status' => 'failed', 'attempt_count' => 5, 'last_attempted_at' => now()->subDays(2)]);
    Http::fake(['https://files.ndias.com/*' => Http::response(ndiPngBytes(), 200, ['Content-Type' => 'image/png'])]);

    $summary = app(NdiProductMediaSynchronizer::class)->sync();

    expect($summary['downloaded'])->toBe(1)
        ->and($source->fresh()->status)->toBe('downloaded');
});

test('force sync retries a failed source immediately despite its backoff', function (): void {
    $source = ndiMediaSource(ndiMediaProduct(), ndiMediaUrl());
    $source->update(['status' => 'failed', 'attempt_count' => 1, 'last_attempted_at' => now()]);
    Http::fake(['https://files.ndias.com/*' => Http::response(ndiPngBytes(), 200, ['Content-Type' => 'image/png'])]);

    expect(app(NdiProductMediaSynchronizer::class)->sync(25, true)['downloaded'])->toBe(1)
        ->and($source->fresh()->status)->toBe('downloaded');
});

test('media sync command reports failure when a supplier image download fails', function (): void {
    ndiMediaSource(ndiMediaProduct(), ndiMediaUrl());
    Http::fake(['https://files.ndias.com/*' => Http::response('', 503)]);

    $this->artisan('ndi:media:sync')->assertExitCode(1);
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
    $source->update(['media_asset_id' => $asset->id, 'status' => 'downloaded', 'downloaded_at' => now()]);
    MediaAssignment::create(['media_asset_id' => $asset->id, 'assignable_type' => Product::class, 'assignable_id' => $source->product_id, 'role' => 'primary']);
    Http::fake(['https://files.ndias.com/*' => Http::response($bytes, 200, ['Content-Type' => 'image/png'])]);

    app(NdiProductMediaSynchronizer::class)->sync();

    expect($source->fresh()->media_asset_id)->toBe($asset->id)
        ->and(Storage::disk('public')->get($path))->toBe($bytes);
});

test('it restores a missing supplier primary assignment without downloading again', function (): void {
    $bytes = ndiPngBytes();
    $sha256 = hash('sha256', $bytes);
    $path = 'media/ndi/'.$sha256.'.png';
    Storage::disk('public')->put($path, $bytes);
    $asset = MediaAsset::create([
        'disk' => 'public', 'path' => $path, 'original_name' => 'supplier.png', 'mime' => 'image/png',
        'byte_size' => strlen($bytes), 'width' => 24, 'height' => 16, 'sha256' => $sha256,
        'uploader_id' => null, 'is_active' => true,
    ]);
    $source = ndiMediaSource(ndiMediaProduct(), ndiMediaUrl());
    $source->update(['media_asset_id' => $asset->id, 'status' => 'downloaded', 'downloaded_at' => now()]);
    Http::fake();

    $summary = app(NdiProductMediaSynchronizer::class)->sync();

    expect($summary)->toBe(['downloaded' => 0, 'failed' => 0, 'assigned' => 1])
        ->and(MediaAssignment::query()->where('assignable_id', $source->product_id)->where('role', 'primary')->value('media_asset_id'))->toBe($asset->id);
    Http::assertNothingSent();
});

test('it respects an administrator suppression when reconciling downloaded media', function (): void {
    $bytes = ndiPngBytes();
    $sha256 = hash('sha256', $bytes);
    $path = 'media/ndi/'.$sha256.'.png';
    Storage::disk('public')->put($path, $bytes);
    $asset = MediaAsset::create([
        'disk' => 'public', 'path' => $path, 'original_name' => 'supplier.png', 'mime' => 'image/png',
        'byte_size' => strlen($bytes), 'width' => 24, 'height' => 16, 'sha256' => $sha256,
        'uploader_id' => null, 'is_active' => true,
    ]);
    $source = ndiMediaSource(ndiMediaProduct(), ndiMediaUrl());
    $source->update(['media_asset_id' => $asset->id, 'status' => 'downloaded', 'downloaded_at' => now(), 'is_suppressed' => true]);
    Http::fake();

    expect(app(NdiProductMediaSynchronizer::class)->sync())->toBe(['downloaded' => 0, 'failed' => 0, 'assigned' => 0])
        ->and(MediaAssignment::query()->where('assignable_id', $source->product_id)->exists())->toBeFalse();
    Http::assertNothingSent();
});

test('it treats a deactivated supplier asset as an explicit suppression instead of retrying forever', function (): void {
    $bytes = ndiPngBytes();
    $sha256 = hash('sha256', $bytes);
    $path = 'media/ndi/'.$sha256.'.png';
    Storage::disk('public')->put($path, $bytes);
    $asset = MediaAsset::create([
        'disk' => 'public', 'path' => $path, 'original_name' => 'inactive.png', 'mime' => 'image/png',
        'byte_size' => strlen($bytes), 'width' => 24, 'height' => 16, 'sha256' => $sha256,
        'uploader_id' => null, 'is_active' => false,
    ]);
    $source = ndiMediaSource(ndiMediaProduct(), ndiMediaUrl());
    $source->update(['media_asset_id' => $asset->id, 'status' => 'downloaded', 'downloaded_at' => now()]);
    Http::fake();

    expect(app(NdiProductMediaSynchronizer::class)->sync())->toBe(['downloaded' => 0, 'failed' => 0, 'assigned' => 0])
        ->and($source->fresh()->status)->toBe('downloaded')
        ->and($source->fresh()->is_suppressed)->toBeTrue();
    Http::assertNothingSent();
});

test('it periodically refreshes a stable NDI URL and replaces the supplier assignment only after validation', function (): void {
    config()->set('ndi.media_refresh_days', 7);
    $oldBytes = ndiPngBytes(20, 20);
    $oldSha = hash('sha256', $oldBytes);
    $oldPath = 'media/ndi/'.$oldSha.'.png';
    Storage::disk('public')->put($oldPath, $oldBytes);
    $oldAsset = MediaAsset::create([
        'disk' => 'public', 'path' => $oldPath, 'original_name' => 'old.png', 'mime' => 'image/png',
        'byte_size' => strlen($oldBytes), 'width' => 20, 'height' => 20, 'sha256' => $oldSha,
        'uploader_id' => null, 'is_active' => true,
    ]);
    $source = ndiMediaSource(ndiMediaProduct(), ndiMediaUrl());
    $source->update(['media_asset_id' => $oldAsset->id, 'status' => 'downloaded', 'downloaded_at' => now()->subDays(8)]);
    MediaAssignment::create(['media_asset_id' => $oldAsset->id, 'assignable_type' => Product::class, 'assignable_id' => $source->product_id, 'role' => 'primary']);
    $newBytes = ndiPngBytes(25, 21);
    Http::fake(['https://files.ndias.com/*' => Http::response($newBytes, 200, ['Content-Type' => 'image/png'])]);

    $summary = app(NdiProductMediaSynchronizer::class)->sync();
    $source->refresh();

    expect($summary['downloaded'])->toBe(1)
        ->and($source->status)->toBe('downloaded')
        ->and($source->media_asset_id)->not->toBe($oldAsset->id)
        ->and(MediaAssignment::query()->where('assignable_id', $source->product_id)->where('role', 'primary')->value('media_asset_id'))->toBe($source->media_asset_id);
});

test('a new catalog artifact resets a failed source even when its URL is unchanged', function (): void {
    $source = ndiMediaSource(ndiMediaProduct(), ndiMediaUrl());
    $source->update(['status' => 'failed', 'attempt_count' => 9, 'last_attempted_at' => now(), 'last_error' => 'temporary']);
    $newArtifactId = (string) Str::ulid();
    $supplierId = DB::table('source_artifacts')->where('id', $source->source_artifact_id)->value('supplier_id');
    DB::table('source_artifacts')->insert([
        'id' => $newArtifactId, 'supplier_id' => $supplierId, 'kind' => 'fixture', 'original_filename' => 'media-new.xlsx',
        'storage_key' => 'fixtures/media-'.$newArtifactId.'.xlsx', 'byte_size' => 1, 'sha256' => hash('sha256', $newArtifactId),
        'received_at' => now(), 'is_redacted' => true,
    ]);

    $method = new ReflectionMethod(NdiCatalogPublisher::class, 'publishMediaSource');
    $method->invoke(app(NdiCatalogPublisher::class), $source->product, ndiMediaUrl(), $newArtifactId);

    expect($source->fresh()->status)->toBe('pending')
        ->and($source->fresh()->attempt_count)->toBe(0)
        ->and($source->fresh()->last_error)->toBeNull();
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

function ndiPngBytes(int $width = 24, int $height = 16): string
{
    $file = UploadedFile::fake()->image('ndi.png', $width, $height);

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
