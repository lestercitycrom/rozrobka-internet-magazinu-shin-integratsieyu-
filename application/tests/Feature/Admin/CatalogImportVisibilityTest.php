<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('an administrator can inspect catalog and safe import audit details', function (): void {
    $admin = User::factory()->owner()->create();
    $supplierId = DB::table('suppliers')->insertGetId(['code' => 'ndi', 'name' => 'NDI', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $brandId = DB::table('brands')->insertGetId(['canonical_name' => 'Nord', 'slug' => 'nord', 'created_at' => now(), 'updated_at' => now()]);
    $productId = DB::table('products')->insertGetId(['product_type' => 'tire', 'brand_id' => $brandId, 'model_name' => 'X1', 'title' => 'Nord X1', 'publication_status' => 'draft', 'first_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    $skuId = DB::table('supplier_skus')->insertGetId(['supplier_id' => $supplierId, 'product_id' => $productId, 'external_sku' => 'N-1', 'catalog_state' => 'catalog', 'created_at' => now(), 'updated_at' => now()]);
    $runId = (string) Str::ulid();
    DB::table('import_runs')->insert(['id' => $runId, 'supplier_id' => $supplierId, 'run_type' => 'bundle', 'status' => 'completed', 'trigger' => 'manual', 'parser_version' => '1', 'started_at' => now(), 'finished_at' => now(), 'created_count' => 1, 'updated_count' => 0, 'skipped_count' => 0, 'rejected_count' => 0, 'created_at' => now(), 'updated_at' => now()]);
    $artifactId = (string) Str::ulid();
    DB::table('source_artifacts')->insert(['id' => $artifactId, 'supplier_id' => $supplierId, 'kind' => 'workbook', 'original_filename' => 'catalog.xlsx', 'storage_key' => 'private/secret/path.xlsx', 'byte_size' => 12, 'sha256' => str_repeat('a', 64), 'received_at' => now(), 'is_redacted' => false]);
    $grossListId = DB::table('price_lists')->insertGetId(['supplier_id' => $supplierId, 'code' => 'ndi-file-current', 'currency' => 'NOK', 'tax_mode' => 'gross', 'valid_from' => now(), 'created_at' => now(), 'updated_at' => now()]);
    $netListId = DB::table('price_lists')->insertGetId(['supplier_id' => $supplierId, 'code' => 'ndi-file-current', 'currency' => 'NOK', 'tax_mode' => 'net', 'valid_from' => now(), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('sku_prices')->insert([
        ['supplier_sku_id' => $skuId, 'price_list_id' => $grossListId, 'amount' => '123.4500', 'observed_at' => now(), 'source_artifact_id' => $artifactId, 'created_at' => now(), 'updated_at' => now()],
        ['supplier_sku_id' => $skuId, 'price_list_id' => $netListId, 'amount' => '98.7600', 'observed_at' => now(), 'source_artifact_id' => $artifactId, 'created_at' => now(), 'updated_at' => now()],
    ]);
    $warehouseId = DB::table('warehouses')->insertGetId(['supplier_id' => $supplierId, 'code' => 'vestby', 'name' => 'Vestby', 'country_code' => 'NO', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('current_stock')->insert(['supplier_sku_id' => $skuId, 'warehouse_id' => $warehouseId, 'quantity' => 17, 'observed_at' => now(), 'source_artifact_id' => $artifactId, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('import_run_artifacts')->insert(['import_run_id' => $runId, 'source_artifact_id' => $artifactId, 'dataset_role' => 'catalog', 'parser_version' => '1']);
    DB::table('import_rejections')->insert(['import_run_id' => $runId, 'source_artifact_id' => $artifactId, 'reason' => 'Invalid width', 'raw_row_json' => json_encode(['password' => 'secret-value'])]);
    $this->actingAs($admin)->get('/admin/catalog?q=Nord')->assertOk()->assertSee('Nord X1')->assertSee('easytire-card', false)->assertSee('type="search"', false)->assertSee(route('admin.catalog.publication.preview'), false);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $this->actingAs($admin)->get('/admin/catalog/'.$productId)->assertOk()->assertSee('N-1')->assertSee('123.4500')->assertSee('98.7600')->assertSee('17')->assertSee('ikke utsalgspriser');
    $queriesForOneSku = count(DB::getQueryLog());
    DB::table('supplier_skus')->insert(['supplier_id' => $supplierId, 'product_id' => $productId, 'external_sku' => 'N-2', 'catalog_state' => 'catalog', 'created_at' => now(), 'updated_at' => now()]);
    DB::flushQueryLog();
    $this->actingAs($admin)->get('/admin/catalog/'.$productId)->assertOk()->assertSee('N-2');
    expect(count(DB::getQueryLog()))->toBe($queriesForOneSku);
    $this->actingAs($admin)->get('/admin/imports')->assertOk()->assertSee('NDI');
    $this->actingAs($admin)->get('/admin/imports/'.$runId)->assertOk()->assertSee('catalog')->assertDontSee('private/secret/path.xlsx')->assertDontSee('secret-value');
    $this->actingAs($admin->fresh()->forceFill(['locale' => 'ru']))->get('/admin/imports/'.$runId)
        ->assertOk()->assertSee('Создано')->assertSee('Обновлено')->assertSee('Без изменений')->assertSee('Отклонено');
});

test('a guest cannot access catalog or imports', function (): void {
    $this->get('/admin/catalog')->assertRedirect(route('admin.login'));
    $this->get('/admin/imports')->assertRedirect(route('admin.login'));
});
