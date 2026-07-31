<?php

use App\Modules\NdiImport\NdiArtifactStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('a raw artifact is stored before parsing and deduplicated by supplier and SHA-256', function (): void {
    Storage::fake('local');

    $supplierId = DB::table('suppliers')->insertGetId([
        'code' => 'ndi',
        'name' => 'NDI',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $source = ndiFixture('tires_valid.xlsx');
    $store = app(NdiArtifactStore::class);
    $first = $store->store($supplierId, 'ndi', $source, isRedacted: true);
    $second = $store->store($supplierId, 'ndi', $source, isRedacted: true);

    expect($first->id)->toBe($second->id)
        ->and($first->sha256)->toBe(hash_file('sha256', $source))
        ->and($first->byteSize)->toBe(filesize($source))
        ->and(DB::table('source_artifacts')->count())->toBe(1);

    Storage::disk('local')->assertExists($first->storageKey);
});

test('an existing raw object must still match its recorded SHA-256', function (): void {
    Storage::fake('local');

    $supplierId = DB::table('suppliers')->insertGetId([
        'code' => 'ndi',
        'name' => 'NDI',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $source = ndiFixture('tires_valid.xlsx');
    $store = app(NdiArtifactStore::class);
    $artifact = $store->store($supplierId, 'ndi', $source, isRedacted: true);
    Storage::disk('local')->put($artifact->storageKey, str_repeat('x', $artifact->byteSize));

    expect(fn () => $store->store($supplierId, 'ndi', $source, isRedacted: true))
        ->toThrow(RuntimeException::class, 'failed its SHA-256 check');
});
