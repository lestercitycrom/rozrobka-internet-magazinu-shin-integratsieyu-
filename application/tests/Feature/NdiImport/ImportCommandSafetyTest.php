<?php

use App\Modules\Catalog\Enums\PublicationStatus;
use App\Modules\Catalog\Models\SupplierSku;
use App\Modules\Catalog\ReadModel\CatalogReadService;
use App\Modules\Operations\SystemHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('import command redacts an unexpected source failure', function (): void {
    $missing = storage_path('private/ndi/raw/missing-secret.xlsx');

    $this->artisan('ndi:import', ['files' => [$missing]])
        ->expectsOutputToContain('Import failed unexpectedly. Check the application logs.')
        ->doesntExpectOutputToContain($missing)
        ->doesntExpectOutputToContain('UnableToOpenFile')
        ->assertExitCode(1);
});

test('manual parser replay preserves the original bundle observation time', function (): void {
    $this->freezeTime();
    Storage::fake('local');
    config()->set([
        'ndi.raw_disk' => 'local',
        'ndi.catalog_currency' => 'NOK',
        'ndi.parser_version' => 'manual-replay-1.0',
        'storefront.stock_max_age_minutes' => 180,
        'health.ndi_success_max_age_minutes' => 180,
        'health.require_heartbeats' => false,
        'payments.enabled' => false,
        'ndi_api.sandbox.enabled' => false,
    ]);
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'easytire-manual-import-'.bin2hex(random_bytes(5));
    mkdir($directory, 0700, true);
    $files = [];
    $historicalAt = now()->subHours(4)->timestamp;
    foreach (['tires_valid.xlsx', 'wheels_valid.xlsx', 'stock_all_warehouses.xlsx'] as $name) {
        $path = $directory.DIRECTORY_SEPARATOR.$name;
        copy(ndiFixture($name), $path);
        touch($path, $historicalAt);
        $files[] = $path;
    }

    try {
        $this->artisan('ndi:import', ['files' => $files])->assertSuccessful();
        DB::table('products')->update(['publication_status' => PublicationStatus::Published->value]);
        $firstObservation = DB::table('current_stock')->max('observed_at');
        $sku = SupplierSku::query()->where('external_sku', '0001234567890')->firstOrFail();
        $firstItem = app(CatalogReadService::class)->findPublicSku($sku->external_sku);
        expect(Carbon::parse($firstObservation)->timestamp)->toBe($historicalAt)
            ->and($firstItem)->not->toBeNull()
            ->and($firstItem->stock->canPurchase(1))->toBeFalse()
            ->and(app(SystemHealth::class)->report()['ndi_fresh'])->toBeFalse();

        config()->set('ndi.parser_version', 'manual-replay-2.0');
        foreach ($files as $file) {
            touch($file, now()->subMinute()->timestamp);
        }

        $this->artisan('ndi:import', ['files' => $files])->assertSuccessful();

        $replayedItem = app(CatalogReadService::class)->findPublicSku($sku->external_sku);
        expect(DB::table('import_runs')->where('status', 'succeeded')->count())->toBe(2)
            ->and(DB::table('current_stock')->max('observed_at'))->toBe($firstObservation)
            ->and(DB::table('current_stock')->min('observed_at'))->toBe($firstObservation)
            ->and($replayedItem)->not->toBeNull()
            ->and($replayedItem->stock->canPurchase(1))->toBeFalse()
            ->and(app(SystemHealth::class)->report()['ndi_fresh'])->toBeFalse();
    } finally {
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($directory);
    }
});
