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
use App\Modules\Content\Models\StorefrontSettings;
use App\Modules\NdiApi\NdiSandboxCatalogManager;
use App\Modules\Orders\Enums\FulfilmentMethod;
use App\Modules\Orders\Enums\NdiOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Pricing\Enums\PricingRuleScope;
use App\Modules\Pricing\Models\PricingRule;
use App\Modules\ServiceBooking\Enums\ServiceRequestSource;
use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use App\Modules\ServiceBooking\Models\ServiceRequest;
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

        $this->call([
            CatalogDemoSeeder::class,
            ContentPageSeeder::class,
            ServicePageSeeder::class,
            SeoServicePageSeeder::class,
            CustomerWordingCorrectionSeeder::class,
            HomepageContentSeeder::class,
            CustomerHomepageCollectionsSeeder::class,
            PublicServicePriceSeeder::class,
            CustomerHomepageCompositionSeeder::class,
            ServiceAddonSeeder::class,
            E2eBookingCalendarSeeder::class,
        ]);
        $this->seedHomepageVideoFixture();

        if (config('ndi_api.sandbox.enabled')) {
            app(NdiSandboxCatalogManager::class)->refresh();
        }

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
                'source_tax_mode' => TaxMode::Gross,
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

        $paymentOrder = Order::query()->firstOrNew(['public_reference' => 'ET-E2E-PAYMENT']);
        if (! $paymentOrder->exists) {
            $paymentOrder->id = (string) Str::ulid();
            $paymentOrder->idempotency_key = (string) Str::uuid();
        }
        $paymentOrder->fill([
            'locale' => 'nb',
            'status' => OrderStatus::AwaitingPayment,
            'payment_status' => PaymentStatus::Authorized,
            'ndi_status' => NdiOrderStatus::Accepted,
            'currency' => 'NOK',
            'subtotal_minor' => 2000,
            'total_minor' => 2000,
            'fulfilment_method' => FulfilmentMethod::Pickup,
            'customer_name' => 'E2E Payment Customer',
            'customer_email' => 'e2e-payment@example.test',
            'customer_phone' => '+4790000000',
            'fitment_confirmed' => true,
            'payment_obligation_confirmed' => true,
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'pricing_snapshot_at' => now(),
            'submitted_at' => now(),
        ])->save();
        $paymentOrder->items()->updateOrCreate(
            ['external_sku' => 'NDI-TEST-4765205551649102'],
            [
                'product_title' => 'NDI Test full-cycle test tyre',
                'quantity' => 2,
                'unit_price_minor' => 1000,
                'line_total_minor' => 2000,
                'currency' => 'NOK',
                'product_snapshot' => ['title' => 'NDI Test full-cycle test tyre', 'type' => 'tire', 'is_integration_test' => true],
                'pricing_snapshot' => ['source' => 'e2e_fixture'],
                'source_price_observed_at' => now(),
            ],
        );
        $paymentOrder->paymentAttempt()->updateOrCreate([], [
            'provider' => 'dintero',
            'idempotency_key' => (string) Str::uuid(),
            'provider_reference' => 'T11116915.e2e-transaction',
            'provider_session_reference' => 'T11116915.e2e-session',
            'status' => PaymentStatus::Authorized,
            'amount_minor' => 2000,
            'currency' => 'NOK',
            'payment_product_type' => 'dintero_psp.creditcard',
            'card_last_four' => '4242',
            'started_at' => now(),
            'authorized_at' => now(),
            'authorization_expires_at' => now()->addDays(7),
        ]);

        $this->seedHistoricalServiceRequests();
    }

    private function seedHistoricalServiceRequests(): void
    {
        foreach (['DESKTOP', 'MOBILE-390', 'MOBILE-320'] as $surface) {
            ServiceRequest::query()->updateOrCreate(['reference' => 'SR-E2E-HIST-'.$surface], [
                'submission_token' => (string) Str::uuid(),
                'locale' => 'nb',
                'source' => ServiceRequestSource::Public,
                'created_by_user_id' => null,
                'service_slug' => 'mobilt-dekkskift',
                'fulfilment' => 'workshop',
                'preferred_date' => now()->subDays(10)->format('Y-m-d'),
                'preferred_window' => 'Ettermiddag',
                'vehicle' => 'Tesla Model Y',
                'registration_number' => null,
                'tire_size' => '255/45 R19',
                'dekkhotell' => true,
                'vehicles' => [
                    ['vehicle' => 'Tesla Model Y', 'registration_number' => null, 'tire_size' => '255/45 R19', 'vehicle_type' => 'passenger', 'rim_band' => 'r19', 'storage_plan' => 'renew'],
                    ['vehicle' => 'Volvo XC60', 'registration_number' => 'EV54321', 'tire_size' => '235/55 R18', 'vehicle_type' => 'passenger', 'rim_band' => 'r16-r18', 'storage_plan' => 'none'],
                ],
                'quote_min_minor' => 230000,
                'quote_max_minor' => 230000,
                'quote_currency' => 'NOK',
                'name' => 'E2E Historical '.$surface,
                'email' => null,
                'phone' => '93333333',
                'phone_normalized' => '4793333333',
                'status' => ServiceRequestStatus::New,
                'consent_at' => now()->subMonth(),
            ]);

            $exactStart = now()->addDays(20)->setTime(10, 0)->startOfMinute();
            ServiceRequest::query()->updateOrCreate(['reference' => 'SR-E2E-EXACT-'.$surface], [
                'submission_token' => (string) Str::uuid(),
                'locale' => 'nb',
                'source' => ServiceRequestSource::Public,
                'created_by_user_id' => null,
                'service_slug' => 'mobilt-dekkskift',
                'fulfilment' => 'workshop',
                'preferred_date' => $exactStart->format('Y-m-d'),
                'preferred_window' => '10:00–10:30',
                'booking_slot_key' => 'e2e-locked-'.$surface,
                'booking_starts_at' => $exactStart,
                'booking_ends_at' => $exactStart->copy()->addMinutes(30),
                'vehicle' => 'E2E exact vehicle '.$surface,
                'registration_number' => 'EXACT'.str_replace('-', '', $surface),
                'tire_size' => '205/55 R16',
                'dekkhotell' => true,
                'vehicles' => [[
                    'vehicle' => 'E2E exact vehicle '.$surface,
                    'registration_number' => 'EXACT'.str_replace('-', '', $surface),
                    'tire_size' => '205/55 R16',
                    'vehicle_type' => 'passenger',
                    'rim_band' => 'r16-r18',
                    'storage_plan' => 'renew',
                ]],
                'quote_min_minor' => 135000,
                'quote_max_minor' => 135000,
                'quote_currency' => 'NOK',
                'name' => 'E2E exact customer '.$surface,
                'email' => null,
                'phone' => '94444444',
                'phone_normalized' => '4794444444',
                'status' => ServiceRequestStatus::New,
                'consent_at' => now()->subDay(),
            ]);
        }
    }

    private function seedHomepageVideoFixture(): void
    {
        $sourcePath = base_path('tests/Fixtures/homepage-loop.mp4');
        $bytes = is_file($sourcePath) ? file_get_contents($sourcePath) : false;
        if ($bytes === false) {
            throw new LogicException('E2E homepage video fixture is unavailable.');
        }

        $path = 'media/homepage/e2e/homepage-loop.mp4';
        Storage::disk('public')->put($path, $bytes);
        $asset = MediaAsset::query()->updateOrCreate(['path' => $path], [
            'disk' => 'public', 'original_name' => 'e2e-homepage-loop.mp4', 'mime' => 'video/mp4',
            'byte_size' => strlen($bytes), 'width' => 640, 'height' => 426, 'sha256' => hash('sha256', $bytes),
            'alt_nb' => null, 'alt_ru' => null, 'uploader_id' => null, 'is_active' => true,
        ]);
        StorefrontSettings::query()->whereKey(1)->update(['hotel_video_media_asset_id' => $asset->id]);
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
