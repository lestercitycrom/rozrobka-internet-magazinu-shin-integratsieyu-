<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('the raw import audit schema has its required tables and columns', function (): void {
    foreach ([
        'suppliers', 'import_runs', 'source_artifacts', 'import_run_artifacts', 'import_rejections',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    expect(Schema::hasColumns('import_runs', [
        'id', 'supplier_id', 'run_type', 'status', 'trigger', 'parser_version',
        'started_at', 'finished_at', 'created_count', 'updated_count', 'skipped_count',
        'rejected_count', 'metadata', 'created_at', 'updated_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('source_artifacts', [
            'id', 'supplier_id', 'kind', 'original_filename', 'storage_key', 'byte_size',
            'sha256', 'received_at', 'content_type', 'is_redacted',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('import_rejections', [
            'id', 'import_run_id', 'source_artifact_id', 'sheet_name', 'row_number',
            'external_sku', 'field_name', 'reason', 'raw_row_json', 'created_at', 'updated_at',
        ]))->toBeTrue();
});

test('supplier codes are unique', function (): void {
    DB::table('suppliers')->insert(['code' => 'ndi', 'name' => 'NDI', 'is_active' => true]);

    expect(fn () => DB::table('suppliers')->insert([
        'code' => 'ndi', 'name' => 'Duplicate NDI', 'is_active' => true,
    ]))->toThrow(QueryException::class);
});

test('artifact hashes are unique per supplier', function (): void {
    $supplierId = createSupplier();
    $hash = str_repeat('a', 64);
    createArtifact($supplierId, $hash, 'raw/ndi/export.xlsx');

    expect(fn () => createArtifact($supplierId, $hash, 'raw/ndi/export-copy.xlsx'))
        ->toThrow(QueryException::class);
});

test('an artifact can link to different runs but not twice to the same dataset role in one run', function (): void {
    $supplierId = createSupplier();
    $artifactId = createArtifact($supplierId, str_repeat('b', 64), 'raw/ndi/export.xlsx');
    $firstRunId = createRun($supplierId, '1.0.0');
    $secondRunId = createRun($supplierId, '2.0.0');

    $firstLink = [
        'import_run_id' => $firstRunId,
        'source_artifact_id' => $artifactId,
        'dataset_role' => 'catalog',
        'parser_version' => '1.0.0',
    ];

    DB::table('import_run_artifacts')->insert($firstLink);
    DB::table('import_run_artifacts')->insert([
        'import_run_id' => $secondRunId,
        'source_artifact_id' => $artifactId,
        'dataset_role' => 'catalog',
        'parser_version' => '2.0.0',
    ]);

    expect(DB::table('import_run_artifacts')->count())->toBe(2)
        ->and(fn () => DB::table('import_run_artifacts')->insert($firstLink))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('import_run_artifacts')->insert([
        ...$firstLink,
        'parser_version' => '9.9.9',
    ]))->toThrow(QueryException::class);
});

function createSupplier(): int
{
    return DB::table('suppliers')->insertGetId([
        'code' => 'ndi-'.Str::lower(Str::random(12)),
        'name' => 'NDI',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function createArtifact(int $supplierId, string $hash, string $storageKey): string
{
    $id = (string) Str::ulid();

    DB::table('source_artifacts')->insert([
        'id' => $id,
        'supplier_id' => $supplierId,
        'kind' => 'xlsx',
        'original_filename' => 'export.xlsx',
        'storage_key' => $storageKey,
        'byte_size' => 1024,
        'sha256' => $hash,
        'received_at' => now(),
        'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'is_redacted' => false,
    ]);

    return $id;
}

function createRun(int $supplierId, string $parserVersion): string
{
    $id = (string) Str::ulid();

    DB::table('import_runs')->insert([
        'id' => $id,
        'supplier_id' => $supplierId,
        'run_type' => 'full',
        'status' => 'running',
        'trigger' => 'manual',
        'parser_version' => $parserVersion,
        'started_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}
