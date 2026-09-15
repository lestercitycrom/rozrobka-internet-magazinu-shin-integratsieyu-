<?php

namespace App\Modules\NdiApi;

use App\Modules\Catalog\Enums\CatalogState;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Enums\PublicationStatus;
use App\Modules\Catalog\Enums\TaxMode;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\CurrentStock;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\SkuPrice;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\SupplierSku;
use App\Modules\Catalog\Models\TireSpec;
use App\Modules\Catalog\Models\Warehouse;
use App\Modules\Pricing\Enums\PricingRuleScope;
use App\Modules\Pricing\Models\PricingRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class NdiSandboxCatalogManager
{
    public const PRICE_LIST_CODE = 'ndi-sandbox-fixture';

    public const PRICING_RULE_NAME = 'NDI Sandbox fixture exact price';

    public function refresh(): SupplierSku
    {
        $this->assertAllowed();

        return DB::transaction(function (): SupplierSku {
            $now = now();
            $supplier = Supplier::query()->updateOrCreate(
                ['code' => (string) config('ndi_api.sandbox.supplier_code')],
                [
                    'name' => 'NDI Sandbox',
                    'integration_profile' => NdiIntegrationProfile::SANDBOX,
                    'is_test_fixture' => true,
                    'is_active' => true,
                ],
            );
            $brand = Brand::query()->firstOrCreate(
                ['canonical_name' => 'NDI Test'],
                ['slug' => 'ndi-test'],
            );
            $catalogSku = (string) config('ndi_api.sandbox.catalog_sku');
            $sku = SupplierSku::query()->firstOrNew([
                'supplier_id' => $supplier->id,
                'external_sku' => $catalogSku,
            ]);
            $product = $sku->product ?? new Product(['first_seen_at' => $now]);
            $product->fill([
                'product_type' => ProductType::Tire,
                'brand_id' => $brand->id,
                'model_name' => 'Full-cycle test tyre',
                'title' => 'NDI Sandbox 205/55 R16 – full-cycle test product',
                'publication_status' => PublicationStatus::Published,
                'last_catalog_seen_at' => $now,
            ])->save();
            $sku->fill([
                'product_id' => $product->id,
                'supplier_item_id' => (string) config('ndi_api.sandbox.article_id'),
                'source_description' => 'TEST PRODUCT – Dintero, A2 and C1 integration only',
                'catalog_state' => CatalogState::Catalog,
            ])->save();

            TireSpec::query()->updateOrCreate(['product_id' => $product->id], [
                'width_mm' => '205.00',
                'aspect_ratio' => '55.00',
                'rim_diameter_in' => '16.00',
                'load_index' => '91',
                'speed_symbol' => 'V',
                'construction' => 'Radial',
                'season' => 'All season',
            ]);

            $artifactId = $this->artifactId($supplier->id);
            $warehouse = Warehouse::query()->updateOrCreate(
                ['supplier_id' => $supplier->id, 'code' => 'sandbox'],
                ['name' => 'NDI Sandbox fixture', 'country_code' => 'NO', 'is_active' => true],
            );
            CurrentStock::query()->updateOrCreate(
                ['supplier_sku_id' => $sku->id, 'warehouse_id' => $warehouse->id],
                [
                    // The only approved sandbox order is the fixed two-item NDI fixture.
                    // Never publish less stock than that order requires, even when a
                    // staging environment variable was accidentally lowered.
                    'quantity' => max(
                        NdiSandboxContract::QUANTITY,
                        (int) config('ndi_api.sandbox.stock_quantity', 2),
                    ),
                    'observed_at' => $now,
                    'source_artifact_id' => $artifactId,
                ],
            );

            $priceList = PriceList::query()->updateOrCreate([
                'supplier_id' => $supplier->id,
                'code' => self::PRICE_LIST_CODE,
                'currency' => NdiSandboxContract::CURRENCY,
                'tax_mode' => TaxMode::Gross,
                'valid_from' => '2000-01-01 00:00:00',
            ], ['valid_to' => null]);
            $priceMinor = NdiSandboxContract::UNIT_PRICE_MINOR;
            SkuPrice::query()->updateOrCreate(
                ['supplier_sku_id' => $sku->id, 'price_list_id' => $priceList->id],
                [
                    'amount' => number_format($priceMinor / 100, 2, '.', ''),
                    'observed_at' => $now,
                    'source_artifact_id' => $artifactId,
                ],
            );
            PricingRule::query()->updateOrCreate(
                ['name' => self::PRICING_RULE_NAME],
                [
                    'scope' => PricingRuleScope::Sku,
                    'scope_key' => $catalogSku,
                    'source_price_list_code' => self::PRICE_LIST_CODE,
                    'source_tax_mode' => TaxMode::Gross,
                    'currency' => NdiSandboxContract::CURRENCY,
                    'markup_basis_points' => 0,
                    'fixed_markup_minor' => 0,
                    'rounding_increment_minor' => 1,
                    'priority' => 10000,
                    'is_active' => true,
                ],
            );

            return $sku->refresh();
        }, 3);
    }

    public function disable(): void
    {
        Supplier::query()
            ->where('code', (string) config('ndi_api.sandbox.supplier_code'))
            ->update(['is_active' => false]);
        PricingRule::query()->where('name', self::PRICING_RULE_NAME)->update(['is_active' => false]);
    }

    private function assertAllowed(): void
    {
        if (app()->environment('production')) {
            throw new LogicException('The NDI sandbox catalog is forbidden in production.');
        }
        if (! config('ndi_api.sandbox.enabled', false)) {
            throw new LogicException('The NDI sandbox catalog feature is disabled.');
        }
        if ((string) config('ndi_api.sandbox.party_id') !== NdiSandboxContract::PARTY_ID
            || (string) config('ndi_api.sandbox.article_id') !== NdiSandboxContract::ARTICLE_ID
            || (string) config('ndi_api.sandbox.catalog_sku') !== NdiSandboxContract::CATALOG_SKU) {
            throw new LogicException('The NDI sandbox fixture must use the supplier-approved PartyID and article.');
        }
        if ((int) config('ndi_api.sandbox.quantity', NdiSandboxContract::QUANTITY) !== NdiSandboxContract::QUANTITY
            || (int) config('ndi_api.sandbox.price_minor', NdiSandboxContract::UNIT_PRICE_MINOR) !== NdiSandboxContract::UNIT_PRICE_MINOR) {
            throw new LogicException('The NDI sandbox fixture must use the supplier-approved test quantity 2.');
        }
    }

    private function artifactId(int $supplierId): string
    {
        $storageKey = 'fixtures/ndi-sandbox-catalog.json';
        $existing = DB::table('source_artifacts')->where('storage_key', $storageKey)->value('id');
        if ($existing !== null) {
            return (string) $existing;
        }

        $id = (string) Str::ulid();
        DB::table('source_artifacts')->insert([
            'id' => $id,
            'supplier_id' => $supplierId,
            'kind' => 'integration_fixture',
            'original_filename' => 'ndi-sandbox-catalog.json',
            'storage_key' => $storageKey,
            'byte_size' => 0,
            'sha256' => hash('sha256', 'easytire-ndi-sandbox-catalog-v1'),
            'received_at' => now(),
            'content_type' => 'application/json',
            'is_redacted' => true,
        ]);

        return $id;
    }
}
