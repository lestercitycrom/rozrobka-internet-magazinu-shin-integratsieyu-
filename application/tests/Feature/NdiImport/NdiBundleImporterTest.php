<?php

use App\Modules\Cart\CartResolver;
use App\Modules\Cart\SessionCart;
use App\Modules\Catalog\Enums\CatalogState;
use App\Modules\Catalog\Enums\PublicationStatus;
use App\Modules\Catalog\Enums\TaxMode;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\SkuPrice;
use App\Modules\Catalog\Models\SkuPriceHistory;
use App\Modules\Catalog\Models\SupplierSku;
use App\Modules\Catalog\ReadModel\CatalogReadService;
use App\Modules\NdiImport\Exceptions\NdiPublishFailed;
use App\Modules\NdiImport\NdiBundleImporter;
use App\Modules\NdiImport\NdiCatalogPublisher;
use App\Modules\Orders\CreatePendingOrder;
use App\Modules\Orders\Data\CheckoutData;
use App\Modules\Orders\Enums\FulfilmentMethod;
use App\Modules\Payments\ConfirmCurrentOrderPaymentReadiness;
use App\Modules\Payments\Exceptions\PaymentOperationRejected;
use App\Modules\Pricing\Enums\PricingRuleScope;
use App\Modules\Pricing\Models\PricingRule;
use App\Modules\Pricing\SellingPriceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('ndi-bundle-import-test');
    config()->set('ndi.raw_disk', 'ndi-bundle-import-test');
    config()->set('ndi.catalog_currency', 'NOK');
    config()->set('ndi.parser_version', 'test-1.0.0');
});

test('a complete valid NDI bundle is published transactionally with provenance', function (): void {
    $outcome = app(NdiBundleImporter::class)->import(validNdiBundle(), 'test', isRedacted: true);

    expect($outcome->status)->toBe('succeeded')
        ->and($outcome->published())->toBeTrue()
        ->and($outcome->createdCount)->toBeGreaterThan(0)
        ->and(DB::table('products')->count())->toBe(6)
        ->and(DB::table('supplier_skus')->count())->toBe(7)
        ->and(DB::table('sku_prices')->count())->toBe(12)
        ->and(DB::table('sku_price_history')->count())->toBe(12)
        ->and(DB::table('price_lists')->pluck('valid_from')->unique()->values()->all())
        ->toBe(['2000-01-01 00:00:00'])
        ->and(DB::table('stock_snapshots')->count())->toBe(49)
        ->and(DB::table('current_stock')->count())->toBe(49)
        ->and(DB::table('source_artifacts')->count())->toBe(3)
        ->and(DB::table('import_run_artifacts')->where('import_run_id', $outcome->runId)->count())->toBe(3);

    $leadingZeroSku = SupplierSku::query()->where('external_sku', '0001234567890')->firstOrFail();
    $stockOnlySku = SupplierSku::query()->where('external_sku', 'TEST-STOCK-ONLY-001')->firstOrFail();

    expect($leadingZeroSku->external_sku)->toBe('0001234567890')
        ->and(strlen($leadingZeroSku->external_sku))->toBe(13)
        ->and($leadingZeroSku->product)->not->toBeNull()
        ->and($stockOnlySku->catalog_state)->toBe(CatalogState::StockOnly)
        ->and($stockOnlySku->product_id)->toBeNull();

    foreach (DB::table('source_artifacts')->pluck('storage_key') as $storageKey) {
        Storage::disk((string) config('ndi.raw_disk'))->assertExists($storageKey);
    }
});

test('repeating the exact successful bundle and parser version is a no-op duplicate run', function (): void {
    $importer = app(NdiBundleImporter::class);
    $first = $importer->import(validNdiBundle(), 'test', isRedacted: true);
    $before = catalogStateCounts();
    $second = $importer->import(validNdiBundle(), 'test', isRedacted: true);

    expect($first->status)->toBe('succeeded')
        ->and($second->status)->toBe('duplicate')
        ->and(catalogStateCounts())->toBe($before)
        ->and(DB::table('source_artifacts')->count())->toBe(3)
        ->and(DB::table('import_runs')->count())->toBe(2)
        ->and(DB::table('import_run_artifacts')->count())->toBe(6);
});

test('an invalid decimal fails the new run and leaves the last published state unchanged', function (): void {
    $importer = app(NdiBundleImporter::class);
    $published = $importer->import(validNdiBundle(), 'test', isRedacted: true);
    $before = catalogStateCounts();

    $failed = $importer->import([
        ndiFixture('invalid_decimal.xlsx'),
        ndiFixture('wheels_valid.xlsx'),
        ndiFixture('stock_all_warehouses.xlsx'),
    ], 'test', isRedacted: true);

    $rejection = DB::table('import_rejections')->where('import_run_id', $failed->runId)->first();

    expect($published->status)->toBe('succeeded')
        ->and($failed->status)->toBe('failed')
        ->and($failed->rejectedCount)->toBe(1)
        ->and($rejection->row_number)->toBe(2)
        ->and($rejection->field_name)->toBe('Net_Price')
        ->and(catalogStateCounts())->toBe($before);
});

test('an incomplete bundle is quarantined before publication', function (): void {
    $outcome = app(NdiBundleImporter::class)->import([
        ndiFixture('tires_valid.xlsx'),
        ndiFixture('wheels_valid.xlsx'),
    ], 'test', isRedacted: true);

    expect($outcome->status)->toBe('failed')
        ->and($outcome->rejectedCount)->toBe(1)
        ->and(DB::table('products')->count())->toBe(0)
        ->and(DB::table('source_artifacts')->count())->toBe(2)
        ->and(DB::table('import_rejections')->where('import_run_id', $outcome->runId)->value('reason'))
        ->toBe('missing_dataset_roles:stock');
});

test('validated catalog and stock files can publish independently for their real delivery cadence', function (): void {
    $importer = app(NdiBundleImporter::class);
    $catalog = $importer->importAvailable([ndiFixture('tires_valid.xlsx')], 'scheduled-inbox', isRedacted: true);

    expect($catalog->status)->toBe('succeeded')
        ->and(DB::table('products')->count())->toBeGreaterThan(0)
        ->and(DB::table('sku_prices')->count())->toBeGreaterThan(0)
        ->and(DB::table('current_stock')->count())->toBe(0)
        ->and(DB::table('import_runs')->where('id', $catalog->runId)->value('run_type'))->toBe('partial_feed');

    $stock = $importer->importAvailable([ndiFixture('stock_all_warehouses.xlsx')], 'scheduled-inbox', isRedacted: true);
    expect($stock->status)->toBe('succeeded')
        ->and(DB::table('current_stock')->count())->toBeGreaterThan(0)
        ->and(DB::table('import_run_artifacts')->where('import_run_id', $stock->runId)->value('dataset_role'))->toBe('stock');
});

test('out of order catalog deliveries preserve the newest current price and retain history', function (): void {
    $this->freezeTime();
    config()->set([
        'storefront.price_max_age_minutes' => 180,
        'storefront.stock_max_age_minutes' => 180,
        'payments.enabled' => false,
        'ndi_api.sandbox.enabled' => false,
    ]);

    $accepted = ndiTirePriceFixture('125.50', '100.00');
    $equalTimeCorrection = ndiTirePriceFixture('126.50', '101.00');
    $lateOlder = ndiTirePriceFixture('95.50', '80.00');
    $newerDecrease = ndiTirePriceFixture('115.50', '90.00');

    try {
        $importer = app(NdiBundleImporter::class);
        $clock = CarbonImmutable::now()->startOfSecond();
        $acceptedAt = $clock->subHour();
        $olderAt = $clock->subHours(2);
        $newerAt = $clock->subMinute();

        $importer->importAvailable([$accepted], 'price-accepted', observedAt: $acceptedAt);
        $importer->importAvailable([ndiFixture('stock_all_warehouses.xlsx')], 'stock-accepted', observedAt: $acceptedAt);
        DB::table('products')->update(['publication_status' => PublicationStatus::Published->value]);

        PricingRule::query()->create([
            'name' => 'Imported gross retail price',
            'scope' => PricingRuleScope::Global,
            'scope_key' => null,
            'source_price_list_code' => 'ndi-file-current',
            'source_tax_mode' => TaxMode::Gross,
            'currency' => 'NOK',
            'markup_basis_points' => 0,
            'fixed_markup_minor' => 0,
            'rounding_increment_minor' => 1,
            'priority' => 100,
            'is_active' => true,
        ]);

        $sku = SupplierSku::query()->where('external_sku', '0001234567890')->firstOrFail();
        $importer->importAvailable([$equalTimeCorrection], 'price-equal-time-correction', observedAt: $acceptedAt);
        $equalTimeArtifactId = DB::table('source_artifacts')
            ->where('sha256', hash_file('sha256', $equalTimeCorrection))
            ->value('id');
        $acceptedCurrent = ndiCurrentPriceState($sku->id);

        expect($acceptedCurrent['gross'])->toBe([
            'amount' => '126.5000',
            'observed_at' => $acceptedAt->format('Y-m-d H:i:s'),
            'source_artifact_id' => $equalTimeArtifactId,
        ])->and($acceptedCurrent['net'])->toBe([
            'amount' => '101.0000',
            'observed_at' => $acceptedAt->format('Y-m-d H:i:s'),
            'source_artifact_id' => $equalTimeArtifactId,
        ])->and(DB::table('sku_prices')
            ->join('price_lists', 'price_lists.id', '=', 'sku_prices.price_list_id')
            ->where('sku_prices.supplier_sku_id', $sku->id)
            ->orderBy('price_lists.tax_mode')
            ->get(['price_lists.code', 'price_lists.currency', 'price_lists.tax_mode', 'price_lists.valid_from'])
            ->map(fn (object $identity): array => (array) $identity)
            ->all())->toBe([
                ['code' => 'ndi-file-current', 'currency' => 'NOK', 'tax_mode' => 'gross', 'valid_from' => '2000-01-01 00:00:00'],
                ['code' => 'ndi-file-current', 'currency' => 'NOK', 'tax_mode' => 'net', 'valid_from' => '2000-01-01 00:00:00'],
            ]);

        $lateOutcome = $importer->importAvailable([$lateOlder], 'price-late-older', observedAt: $olderAt);
        $lateArtifactId = DB::table('source_artifacts')->where('sha256', hash_file('sha256', $lateOlder))->value('id');
        $lateHistory = SkuPriceHistory::query()
            ->with('priceList')
            ->where('supplier_sku_id', $sku->id)
            ->where('source_artifact_id', $lateArtifactId)
            ->get()
            ->mapWithKeys(fn (SkuPriceHistory $price): array => [
                $price->priceList->tax_mode->value => [
                    'amount' => $price->amount,
                    'observed_at' => $price->observed_at->format('Y-m-d H:i:s'),
                    'source_artifact_id' => $price->source_artifact_id,
                ],
            ])
            ->sortKeys()
            ->all();

        expect($lateOutcome->status)->toBe('succeeded')
            ->and(ndiCurrentPriceState($sku->id))->toBe($acceptedCurrent)
            ->and($lateHistory)->toBe([
                'gross' => [
                    'amount' => '95.5000',
                    'observed_at' => $olderAt->format('Y-m-d H:i:s'),
                    'source_artifact_id' => $lateArtifactId,
                ],
                'net' => [
                    'amount' => '80.0000',
                    'observed_at' => $olderAt->format('Y-m-d H:i:s'),
                    'source_artifact_id' => $lateArtifactId,
                ],
            ])
            ->and(DB::table('entity_changes')
                ->where('import_run_id', $lateOutcome->runId)
                ->where('entity_type', 'SkuPrice')
                ->count())->toBe(0);

        $item = app(CatalogReadService::class)->findPublicSku($sku->external_sku);
        $sellingPrice = $item === null ? null : app(SellingPriceService::class)->forItem($item);
        app(SessionCart::class)->add($sku->external_sku, 1);
        $cart = app(CartResolver::class)->resolve();
        $order = app(CreatePendingOrder::class)->execute(new CheckoutData(
            idempotencyKey: (string) Str::uuid(),
            locale: 'nb',
            customerName: 'Price Provenance Buyer',
            customerEmail: 'price-provenance@example.test',
            customerPhone: '+47 900 00 000',
            fulfilmentMethod: FulfilmentMethod::Pickup,
            vehicleRegistration: null,
            customerNote: null,
        ), $cart)->order;

        expect($item)->not->toBeNull()
            ->and($sellingPrice?->money->minor)->toBe(12650)
            ->and($cart->issues)->toBe([])
            ->and($cart->lines)->toHaveCount(1)
            ->and($cart->lines[0]->price->money->minor)->toBe(12650)
            ->and($order->items()->sole()->unit_price_minor)->toBe(12650)
            ->and(fn () => app(ConfirmCurrentOrderPaymentReadiness::class)->assertReady($order->refresh()))
            ->not->toThrow(Throwable::class);

        $importer->importAvailable([$newerDecrease], 'price-newer-decrease', observedAt: $newerAt);
        $newerArtifactId = DB::table('source_artifacts')->where('sha256', hash_file('sha256', $newerDecrease))->value('id');
        $newerCurrent = ndiCurrentPriceState($sku->id);
        $newerItem = app(CatalogReadService::class)->findPublicSku($sku->external_sku);
        $newerSellingPrice = $newerItem === null ? null : app(SellingPriceService::class)->forItem($newerItem);
        $newerCart = app(CartResolver::class)->resolve();

        expect($newerCurrent['gross'])->toBe([
            'amount' => '115.5000',
            'observed_at' => $newerAt->format('Y-m-d H:i:s'),
            'source_artifact_id' => $newerArtifactId,
        ])->and($newerCurrent['net'])->toBe([
            'amount' => '90.0000',
            'observed_at' => $newerAt->format('Y-m-d H:i:s'),
            'source_artifact_id' => $newerArtifactId,
        ])->and($newerSellingPrice?->money->minor)->toBe(11550)
            ->and($newerCart->issues)->toBe([])
            ->and($newerCart->lines[0]->price->money->minor)->toBe(11550)
            ->and(DB::table('sku_price_history')->where('supplier_sku_id', $sku->id)->count())->toBe(8)
            ->and(fn () => app(ConfirmCurrentOrderPaymentReadiness::class)->assertReady($order->refresh()))
            ->toThrow(PaymentOperationRejected::class, 'selling price');
    } finally {
        @unlink($accepted);
        @unlink($equalTimeCorrection);
        @unlink($lateOlder);
        @unlink($newerDecrease);
    }
});

test('empty and sharply truncated stock snapshots are quarantined without zeroing the current catalog', function (): void {
    config()->set('ndi.stock_drop_guard_min_baseline', 1);
    config()->set('ndi.stock_minimum_previous_ratio', 0.9);
    $importer = app(NdiBundleImporter::class);
    $initial = $importer->importAvailable([ndiFixture('stock_all_warehouses.xlsx')], 'scheduled-inbox', isRedacted: true);
    $before = DB::table('current_stock')
        ->orderBy('supplier_sku_id')->orderBy('warehouse_id')
        ->get(['supplier_sku_id', 'warehouse_id', 'quantity', 'observed_at'])
        ->map(fn (object $row): array => (array) $row)
        ->all();
    $empty = ndiStockFixtureWithRows(0);
    $truncated = ndiStockFixtureWithRows(3);

    try {
        $emptyOutcome = $importer->importAvailable([$empty], 'scheduled-inbox', isRedacted: true);
        $truncatedOutcome = $importer->importAvailable([$truncated], 'scheduled-inbox', isRedacted: true);

        expect($initial->status)->toBe('succeeded')
            ->and($emptyOutcome->status)->toBe('failed')
            ->and(DB::table('import_rejections')->where('import_run_id', $emptyOutcome->runId)->pluck('reason'))
            ->toContain('dataset_contains_no_rows')
            ->and($truncatedOutcome->status)->toBe('failed')
            ->and(DB::table('import_rejections')->where('import_run_id', $truncatedOutcome->runId)->pluck('reason'))
            ->toContain('stock_row_count_drop_requires_manual_override')
            ->and(DB::table('current_stock')
                ->orderBy('supplier_sku_id')->orderBy('warehouse_id')
                ->get(['supplier_sku_id', 'warehouse_id', 'quantity', 'observed_at'])
                ->map(fn (object $row): array => (array) $row)
                ->all())->toBe($before);

        $approved = $importer->importAvailable(
            [$truncated],
            'manual-approved-stock-drop',
            isRedacted: true,
            allowStockDrop: true,
        );
        expect($approved->status)->toBe('succeeded');
    } finally {
        @unlink($empty);
        @unlink($truncated);
    }
});

test('a publisher failure rolls back all domain changes and records a failed run', function (): void {
    $publisher = Mockery::mock(NdiCatalogPublisher::class);
    $publisher->shouldReceive('publish')->once()->andReturnUsing(function (): never {
        Brand::query()->create(['canonical_name' => 'TEST ROLLBACK', 'slug' => 'test-rollback']);

        throw new RuntimeException('Synthetic publish failure.');
    });
    app()->instance(NdiCatalogPublisher::class, $publisher);

    try {
        app(NdiBundleImporter::class)->import(validNdiBundle(), 'test', isRedacted: true);
        test()->fail('The synthetic publisher failure was not raised.');
    } catch (NdiPublishFailed $exception) {
        expect(Brand::query()->where('slug', 'test-rollback')->exists())->toBeFalse()
            ->and(DB::table('products')->count())->toBe(0)
            ->and(DB::table('import_runs')->where('id', $exception->runId)->value('status'))->toBe('failed');
    }
});

/** @return list<string> */
function validNdiBundle(): array
{
    return [
        ndiFixture('tires_valid.xlsx'),
        ndiFixture('wheels_valid.xlsx'),
        ndiFixture('stock_all_warehouses.xlsx'),
    ];
}

/** @return array<string, int> */
function catalogStateCounts(): array
{
    return [
        'products' => DB::table('products')->count(),
        'supplier_skus' => DB::table('supplier_skus')->count(),
        'prices' => DB::table('sku_prices')->count(),
        'price_history' => DB::table('sku_price_history')->count(),
        'stock_snapshots' => DB::table('stock_snapshots')->count(),
        'current_stock' => DB::table('current_stock')->count(),
        'entity_changes' => DB::table('entity_changes')->count(),
    ];
}

/** @return array<string, array{amount: string, observed_at: string, source_artifact_id: string}> */
function ndiCurrentPriceState(int $supplierSkuId): array
{
    return SkuPrice::query()
        ->with('priceList')
        ->where('supplier_sku_id', $supplierSkuId)
        ->get()
        ->mapWithKeys(fn (SkuPrice $price): array => [
            $price->priceList->tax_mode->value => [
                'amount' => $price->amount,
                'observed_at' => $price->observed_at->format('Y-m-d H:i:s'),
                'source_artifact_id' => $price->source_artifact_id,
            ],
        ])
        ->sortKeys()
        ->all();
}

function ndiTirePriceFixture(string $gross, string $net): string
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'easytire-price-'.bin2hex(random_bytes(6)).'.xlsx';
    if (! copy(ndiFixture('tires_valid.xlsx'), $path)) {
        throw new RuntimeException('Unable to create a temporary NDI price fixture.');
    }

    $zip = new ZipArchive;
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Unable to open the temporary NDI price fixture.');
    }

    $entry = 'xl/worksheets/sheet1.xml';
    $xml = $zip->getFromName($entry);
    if (! is_string($xml)) {
        $zip->close();

        throw new RuntimeException('Unable to read the temporary NDI price worksheet.');
    }

    foreach (['N2' => $gross, 'O2' => $net] as $cell => $amount) {
        $count = 0;
        $updated = preg_replace_callback(
            '~(<x:c r="'.preg_quote($cell, '~').'"[^>]*>.*?<x:v>)[^<]*(</x:v>)~s',
            fn (array $match): string => $match[1].htmlspecialchars($amount, ENT_XML1 | ENT_QUOTES, 'UTF-8').$match[2],
            $xml,
            1,
            $count,
        );
        if (! is_string($updated) || $count !== 1) {
            $zip->close();

            throw new RuntimeException("Unable to update {$cell} in the temporary NDI price fixture.");
        }
        $xml = $updated;
    }

    if (! $zip->addFromString($entry, $xml)) {
        $zip->close();

        throw new RuntimeException('Unable to save the temporary NDI price fixture.');
    }
    $zip->close();

    return $path;
}

function ndiStockFixtureWithRows(int $dataRows): string
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ndi-stock-'.bin2hex(random_bytes(6)).'.xlsx';
    if (! copy(ndiFixture('stock_all_warehouses.xlsx'), $path)) {
        throw new RuntimeException('Unable to create the temporary stock workbook.');
    }

    $zip = new ZipArchive;
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Unable to open the temporary stock workbook.');
    }

    $entry = 'xl/worksheets/sheet1.xml';
    $xml = $zip->getFromName($entry);
    if (! is_string($xml)) {
        $zip->close();
        throw new RuntimeException('Unable to read the stock worksheet.');
    }

    $lastKeptRow = $dataRows + 1;
    $xml = preg_replace_callback(
        '/<(?:[A-Za-z0-9_]+:)?row\b[^>]*\br="(\d+)"[^>]*>.*?<\/(?:[A-Za-z0-9_]+:)?row>/s',
        fn (array $match): string => (int) $match[1] <= $lastKeptRow ? $match[0] : '',
        $xml,
    );
    if (! is_string($xml) || ! $zip->addFromString($entry, $xml)) {
        $zip->close();
        throw new RuntimeException('Unable to truncate the stock worksheet.');
    }
    $zip->close();

    return $path;
}
