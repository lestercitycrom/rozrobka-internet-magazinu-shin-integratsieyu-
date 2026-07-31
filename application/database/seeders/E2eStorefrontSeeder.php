<?php

namespace Database\Seeders;

use App\Modules\Catalog\Enums\TaxMode;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\SkuPrice;
use App\Modules\Catalog\Models\SupplierSku;
use App\Modules\Orders\Enums\FulfilmentMethod;
use App\Modules\Orders\Enums\NdiOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Pricing\Enums\PricingRuleScope;
use App\Modules\Pricing\Models\PricingRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class E2eStorefrontSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['testing', 'local'])) {
            throw new LogicException('E2E storefront fixtures are restricted to testing/local.');
        }

        $this->call([CatalogDemoSeeder::class, ServicePageSeeder::class]);

        $sku = SupplierSku::query()->where('external_sku', '000205550016')->firstOrFail();
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
}
