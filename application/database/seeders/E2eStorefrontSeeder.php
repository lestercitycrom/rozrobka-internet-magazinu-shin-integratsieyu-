<?php

namespace Database\Seeders;

use App\Modules\Catalog\Enums\TaxMode;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\SkuPrice;
use App\Modules\Catalog\Models\SupplierSku;
use App\Modules\Commerce\Enums\ServiceAddonApplicability;
use App\Modules\Commerce\Enums\ServiceAddonBillingMode;
use App\Modules\Commerce\Models\ServiceAddon;
use App\Modules\Content\Models\MediaAsset;
use App\Modules\Content\Models\MediaAssignment;
use App\Modules\Orders\Enums\FulfilmentMethod;
use App\Modules\Orders\Enums\NdiOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Pricing\Enums\PricingRuleScope;
use App\Modules\Pricing\Models\PricingRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;

class E2eStorefrontSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['testing', 'local'])) {
            throw new LogicException('E2E storefront fixtures are restricted to testing/local.');
        }

        $this->call([CatalogDemoSeeder::class, ServicePageSeeder::class, ServiceAddonSeeder::class]);

        ServiceAddon::query()->updateOrCreate(['code' => 'e2e-wheel-care'], [
            'title_nb' => 'E2E felgservice',
            'title_ru' => 'E2E-сервис для дисков',
            'applicability' => ServiceAddonApplicability::Wheel,
            'billing_mode' => ServiceAddonBillingMode::PerItem,
            'currency' => 'NOK',
            'price_minor' => 9900,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        ServiceAddon::query()->updateOrCreate(['code' => 'e2e-tire-care'], [
            'title_nb' => 'E2E dekkservice',
            'title_ru' => 'E2E-сервис для шин',
            'applicability' => ServiceAddonApplicability::Tire,
            'billing_mode' => ServiceAddonBillingMode::PerOrder,
            'currency' => 'NOK',
            'price_minor' => 7900,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $sku = SupplierSku::query()->where('external_sku', '000205550016')->firstOrFail();
        $wheelSku = SupplierSku::query()->where('external_sku', 'DEMO-WHEEL-18112')->firstOrFail();
        $this->seedDemoProductMedia($sku);
        $priceList = PriceList::query()->firstOrCreate([
            'supplier_id' => $sku->supplier_id,
            'code' => 'public',
            'currency' => 'NOK',
            'tax_mode' => TaxMode::Gross,
            'valid_from' => '2026-01-01 00:00:00',
        ]);
        SkuPrice::query()->updateOrCreate(
            ['supplier_sku_id' => $sku->id, 'price_list_id' => $priceList->id],
            [
                'amount' => '1499.0000',
                'observed_at' => now(),
                'source_artifact_id' => DB::table('source_artifacts')
                    ->where('storage_key', 'demo/catalog-seed.xlsx')
                    ->value('id'),
            ],
        );
        SkuPrice::query()->updateOrCreate(
            ['supplier_sku_id' => $wheelSku->id, 'price_list_id' => $priceList->id],
            [
                'amount' => '2199.0000',
                'observed_at' => now(),
                'source_artifact_id' => DB::table('source_artifacts')
                    ->where('storage_key', 'demo/catalog-seed.xlsx')
                    ->value('id'),
            ],
        );
        PricingRule::query()->updateOrCreate(
            ['name' => 'E2E public catalog price'],
            [
                'scope' => PricingRuleScope::Global,
                'scope_key' => null,
                'source_price_list_code' => 'public',
                'currency' => 'NOK',
                'markup_basis_points' => 0,
                'fixed_markup_minor' => 0,
                'rounding_increment_minor' => 100,
                'priority' => 1000,
                'is_active' => true,
                'starts_at' => '2026-01-01 00:00:00',
                'ends_at' => null,
            ],
        );

        $order = Order::query()->firstOrNew(['public_reference' => 'ET-E2E-ORDER']);
        if (! $order->exists) {
            $order->id = (string) Str::ulid();
            $order->idempotency_key = (string) Str::uuid();
        }

        $order->fill([
            'locale' => 'nb',
            'status' => OrderStatus::PendingReview,
            'payment_status' => PaymentStatus::NotStarted,
            'ndi_status' => NdiOrderStatus::NotSubmitted,
            'currency' => 'NOK',
            'subtotal_minor' => 149900,
            'total_minor' => 149900,
            'fulfilment_method' => FulfilmentMethod::Pickup,
            'customer_name' => 'E2E Customer',
            'customer_email' => 'e2e-order@example.test',
            'customer_phone' => '00000000',
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'pricing_snapshot_at' => now(),
            'submitted_at' => now(),
        ])->save();

        $order->items()->updateOrCreate(
            ['external_sku' => '000205550016'],
            [
                'product_title' => 'Demo 205/55 R16',
                'quantity' => 1,
                'unit_price_minor' => 149900,
                'line_total_minor' => 149900,
                'currency' => 'NOK',
                'product_snapshot' => ['title' => 'Demo 205/55 R16', 'type' => 'tire'],
                'pricing_snapshot' => ['source' => 'e2e_fixture'],
                'source_price_observed_at' => now(),
            ],
        );
    }

    private function seedDemoProductMedia(SupplierSku $sku): void
    {
        $bytes = file_get_contents(public_path('brand/apple-touch-icon.png'));
        $dimensions = $bytes === false ? false : getimagesizefromstring($bytes);

        if ($bytes === false || $dimensions === false) {
            throw new LogicException('Invalid E2E media fixture.');
        }

        $path = 'media/e2e-demo-product.png';
        Storage::disk('public')->put($path, $bytes);
        $asset = MediaAsset::query()->firstOrNew(['path' => $path]);
        $asset->fill([
            'disk' => 'public',
            'original_name' => 'e2e-demo-product.png',
            'mime' => 'image/png',
            'byte_size' => strlen($bytes),
            'width' => $dimensions[0],
            'height' => $dimensions[1],
            'sha256' => hash('sha256', $bytes),
            'alt_nb' => 'Demo produktbilde',
            'alt_ru' => 'Демонстрационное изображение товара',
            'uploader_id' => null,
            'is_active' => true,
        ])->save();

        MediaAssignment::query()->updateOrCreate([
            'assignable_type' => Product::class,
            'assignable_id' => $sku->product_id,
            'role' => 'primary',
        ], ['media_asset_id' => $asset->getKey()]);
    }
}
