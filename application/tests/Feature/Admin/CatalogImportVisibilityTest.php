<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('an administrator can inspect catalog and safe import audit details', function (): void {
    $admin = User::factory()->operations()->create();
    $supplierId = DB::table('suppliers')->insertGetId(['code' => 'ndi', 'name' => 'NDI', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $brandId = DB::table('brands')->insertGetId(['canonical_name' => 'Nord', 'slug' => 'nord', 'created_at' => now(), 'updated_at' => now()]);
    $productId = DB::table('products')->insertGetId(['product_type' => 'tire', 'brand_id' => $brandId, 'model_name' => 'X1', 'title' => 'Nord X1', 'publication_status' => 'draft', 'first_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('supplier_skus')->insert(['supplier_id' => $supplierId, 'product_id' => $productId, 'external_sku' => 'N-1', 'catalog_state' => 'catalog', 'created_at' => now(), 'updated_at' => now()]);
    $runId = (string) Str::ulid();
    DB::table('import_runs')->insert(['id' => $runId, 'supplier_id' => $supplierId, 'run_type' => 'bundle', 'status' => 'completed', 'trigger' => 'manual', 'parser_version' => '1', 'started_at' => now(), 'finished_at' => now(), 'created_count' => 1, 'updated_count' => 0, 'skipped_count' => 0, 'rejected_count' => 0, 'created_at' => now(), 'updated_at' => now()]);
    $artifactId = (string) Str::ulid();
    DB::table('source_artifacts')->insert(['id' => $artifactId, 'supplier_id' => $supplierId, 'kind' => 'workbook', 'original_filename' => 'catalog.xlsx', 'storage_key' => 'private/secret/path.xlsx', 'byte_size' => 12, 'sha256' => str_repeat('a', 64), 'received_at' => now(), 'is_redacted' => false]);
    DB::table('import_run_artifacts')->insert(['import_run_id' => $runId, 'source_artifact_id' => $artifactId, 'dataset_role' => 'catalog', 'parser_version' => '1']);
    DB::table('import_rejections')->insert(['import_run_id' => $runId, 'source_artifact_id' => $artifactId, 'reason' => 'Invalid width', 'raw_row_json' => json_encode(['password' => 'secret-value'])]);
    $this->actingAs($admin)->get('/admin/catalog?q=Nord')->assertOk()->assertSee('Nord X1');
    $this->actingAs($admin)->get('/admin/catalog/'.$productId)->assertOk()->assertSee('N-1');
    $this->actingAs($admin)->get('/admin/imports')->assertOk()->assertSee('NDI');
    $this->actingAs($admin)->get('/admin/imports/'.$runId)->assertOk()->assertSee('catalog')->assertDontSee('private/secret/path.xlsx')->assertDontSee('secret-value');
    $this->actingAs($admin->fresh()->forceFill(['locale' => 'ru']))->get('/admin/imports/'.$runId)
        ->assertOk()->assertSee('Создано')->assertSee('Обновлено')->assertSee('Без изменений')->assertSee('Отклонено');
});

test('a guest cannot access catalog or imports', function (): void {
    $this->get('/admin/catalog')->assertRedirect(route('admin.login'));
    $this->get('/admin/imports')->assertRedirect(route('admin.login'));
});
