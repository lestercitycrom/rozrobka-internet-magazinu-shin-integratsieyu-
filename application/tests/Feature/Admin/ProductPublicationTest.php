<?php

use App\Models\User;
use App\Modules\Pricing\Enums\PricingRuleScope;
use App\Modules\Pricing\Models\PricingRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
    $admin = User::factory()->owner()->create();

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
    $admin = User::factory()->owner()->create(['locale' => 'ru']);

    $this->actingAs($admin)->get('/admin/catalog/'.$productId)
        ->assertOk()
        ->assertSee('Публикация')
        ->assertSee('Опубликован');
});

test('administrator previews and publishes only draft products with a positive computable retail price', function (): void {
    $admin = User::factory()->owner()->create();
    $eligibleId = pricedDraftProductId('PUBLISH-1', '100.0000');
    $zeroId = pricedDraftProductId('PUBLISH-0', '0.0000');
    PricingRule::create(['name' => 'Test retail', 'scope' => PricingRuleScope::Global, 'source_price_list_code' => 'ndi-file-current', 'currency' => 'NOK', 'markup_basis_points' => 1000, 'fixed_markup_minor' => 0, 'rounding_increment_minor' => 100, 'priority' => 1, 'is_active' => true]);

    $preview = $this->actingAs($admin)->get('/admin/catalog/publication-preview');
    $preview
        ->assertOk()
        ->assertSee('PUBLISH-1')
        ->assertDontSee('PUBLISH-0')
        ->assertSee('110,00 NOK');
    preg_match('/name="publication_snapshot" value="([a-f0-9]{64})"/', $preview->getContent(), $matches);
    expect($matches[1] ?? null)->not->toBeNull();
    $this->actingAs($admin)->put('/admin/catalog/publication', ['publication_snapshot' => $matches[1]])->assertRedirect('/admin/catalog');

    expect(DB::table('products')->where('id', $eligibleId)->value('publication_status'))->toBe('published')
        ->and(DB::table('products')->where('id', $zeroId)->value('publication_status'))->toBe('draft');
});

test('bulk publication fails closed when catalog or prices changed after preview', function (): void {
    $admin = User::factory()->owner()->create();
    $reviewedId = pricedDraftProductId('REVIEWED-1', '100.0000');
    PricingRule::create(['name' => 'Test retail', 'scope' => PricingRuleScope::Global, 'source_price_list_code' => 'ndi-file-current', 'currency' => 'NOK', 'markup_basis_points' => 1000, 'fixed_markup_minor' => 0, 'rounding_increment_minor' => 100, 'priority' => 1, 'is_active' => true]);

    $preview = $this->actingAs($admin)->get('/admin/catalog/publication-preview');
    preg_match('/name="publication_snapshot" value="([a-f0-9]{64})"/', $preview->getContent(), $matches);
    $newId = pricedDraftProductId('UNREVIEWED-2', '200.0000');

    $this->actingAs($admin)
        ->put('/admin/catalog/publication', ['publication_snapshot' => $matches[1]])
        ->assertSessionHasErrors('publication_snapshot');

    expect(DB::table('products')->where('id', $reviewedId)->value('publication_status'))->toBe('draft')
        ->and(DB::table('products')->where('id', $newId)->value('publication_status'))->toBe('draft');
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

function pricedDraftProductId(string $sku, string $amount): int
{
    $supplierId = DB::table('suppliers')->insertGetId(['code' => $sku, 'name' => 'NDI', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $brandId = DB::table('brands')->insertGetId(['canonical_name' => $sku, 'slug' => strtolower($sku), 'created_at' => now(), 'updated_at' => now()]);
    $productId = DB::table('products')->insertGetId(['product_type' => 'tire', 'brand_id' => $brandId, 'model_name' => 'Model', 'title' => $sku, 'publication_status' => 'draft', 'first_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    $skuId = DB::table('supplier_skus')->insertGetId(['supplier_id' => $supplierId, 'product_id' => $productId, 'external_sku' => $sku, 'catalog_state' => 'catalog', 'created_at' => now(), 'updated_at' => now()]);
    $artifactId = (string) Str::ulid();
    DB::table('source_artifacts')->insert(['id' => $artifactId, 'supplier_id' => $supplierId, 'kind' => 'workbook', 'original_filename' => 'fixture.xlsx', 'storage_key' => strtolower($sku).'.xlsx', 'byte_size' => 1, 'sha256' => str_repeat('b', 64), 'received_at' => now(), 'is_redacted' => true]);
    $listId = DB::table('price_lists')->insertGetId(['supplier_id' => $supplierId, 'code' => 'ndi-file-current', 'currency' => 'NOK', 'tax_mode' => 'gross', 'valid_from' => now()->subMinute(), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('sku_prices')->insert(['supplier_sku_id' => $skuId, 'price_list_id' => $listId, 'amount' => $amount, 'observed_at' => now(), 'source_artifact_id' => $artifactId, 'created_at' => now(), 'updated_at' => now()]);

    return $productId;
}
