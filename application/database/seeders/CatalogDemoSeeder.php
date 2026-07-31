<?php

namespace Database\Seeders;

use App\Modules\Catalog\Enums\CatalogState;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Enums\PublicationStatus;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\CurrentStock;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\SupplierSku;
use App\Modules\Catalog\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/** Deterministic fixtures for local UI work and automated tests; never production data. */
class CatalogDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('CatalogDemoSeeder is restricted to local and testing environments.');
        }

        $supplier = Supplier::query()->firstOrCreate(['code' => 'demo-ndi'], ['name' => 'Demo NDI', 'is_active' => true]);
        $brand = Brand::query()->firstOrCreate(['canonical_name' => 'Demo Tyres'], ['slug' => 'demo-tyres']);
        $product = Product::query()->firstOrCreate(['title' => 'Demo 205/55 R16'], [
            'product_type' => ProductType::Tire,
            'brand_id' => $brand->id,
            'model_name' => 'Road Demo',
            'publication_status' => PublicationStatus::Published,
            'first_seen_at' => now(),
        ]);
        $product->tireSpec()->firstOrCreate([], [
            'width_mm' => '205.00', 'aspect_ratio' => '55.00', 'rim_diameter_in' => '16.00',
            'load_index' => '91', 'speed_symbol' => 'V', 'season' => 'Summer',
        ]);
        $sku = SupplierSku::query()->firstOrCreate([
            'supplier_id' => $supplier->id, 'external_sku' => '000205550016',
        ], ['product_id' => $product->id, 'catalog_state' => CatalogState::Catalog]);
        $warehouse = Warehouse::query()->firstOrCreate([
            'supplier_id' => $supplier->id, 'code' => 'demo',
        ], ['name' => 'Demo warehouse', 'is_active' => true]);
        $artifactId = $this->artifactId($supplier->id);
        CurrentStock::query()->firstOrCreate([
            'supplier_sku_id' => $sku->id, 'warehouse_id' => $warehouse->id,
        ], ['quantity' => 4, 'observed_at' => now(), 'source_artifact_id' => $artifactId]);
    }

    private function artifactId(int $supplierId): string
    {
        $id = (string) Str::ulid();
        $existing = DB::table('source_artifacts')->where('storage_key', 'demo/catalog-seed.xlsx')->value('id');
        if ($existing !== null) {
            return $existing;
        }

        DB::table('source_artifacts')->insert([
            'id' => $id, 'supplier_id' => $supplierId, 'kind' => 'fixture',
            'original_filename' => 'catalog-demo.xlsx', 'storage_key' => 'demo/catalog-seed.xlsx',
            'byte_size' => 0, 'sha256' => hash('sha256', 'catalog-demo-seed'), 'received_at' => now(), 'is_redacted' => true,
        ]);

        return $id;
    }
}
