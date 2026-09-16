<?php

use App\Models\User;
use App\Modules\Catalog\CatalogDataMode;
use App\Modules\NdiImport\NdiBundleImporter;
use App\Modules\Operations\SystemHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('snapshot pauses scheduled and direct inbox imports without modifying the saved files or observations', function (): void {
    Http::preventStrayRequests();
    Storage::fake('local');
    config()->set(['ndi.catalog_currency' => 'NOK', 'ndi.catalog_mode' => 'live']);
    app(NdiBundleImporter::class)->importAvailable([ndiFixture('tires_valid.xlsx')], 'snapshot-test-setup');
    $before = DB::table('sku_prices')->orderBy('id')->get()->toJson();
    $runs = DB::table('import_runs')->count();

    config()->set(['ndi.catalog_mode' => 'snapshot', 'ndi.inbox_path' => base_path('tests/Fixtures/Ndi')]);
    $this->artisan('ndi:inbox:sync')
        ->expectsOutputToContain('NDI inbox paused: staging snapshot mode')
        ->assertSuccessful();
    $this->artisan('ndi:import', ['files' => [ndiFixture('tires_valid.xlsx')], '--partial' => true])
        ->expectsOutputToContain('NDI import requires live catalogue mode')
        ->assertFailed();

    expect(DB::table('ndi_inbox_receipts')->count())->toBe(0)
        ->and(DB::table('import_runs')->count())->toBe($runs)
        ->and(DB::table('sku_prices')->orderBy('id')->get()->toJson())->toBe($before);
    Http::assertNothingSent();
});

test('live mode resumes normal inbox validation instead of a successful snapshot no-op', function (): void {
    config()->set(['ndi.catalog_mode' => 'snapshot', 'ndi.inbox_path' => null]);
    $this->artisan('ndi:inbox:sync')->expectsOutputToContain('paused')->assertSuccessful();

    config()->set('ndi.catalog_mode', 'live');
    $this->artisan('ndi:inbox:sync')->expectsOutputToContain('NDI inbox is not configured')->assertFailed();
});

test('snapshot mode is rejected in production and never reported as active', function (): void {
    $this->app['env'] = 'production';
    config()->set('ndi.catalog_mode', 'snapshot');

    expect(CatalogDataMode::isSnapshot())->toBeFalse()
        ->and(CatalogDataMode::isValid())->toBeFalse()
        ->and(CatalogDataMode::mode())->toBe('invalid');
    $this->artisan('ndi:inbox:sync')->expectsOutputToContain('Invalid NDI catalogue mode')->assertFailed();
    expect(DB::table('ndi_inbox_receipts')->count())->toBe(0);
});

test('unknown catalogue mode fails closed without exposing its raw value', function (): void {
    config()->set('ndi.catalog_mode', 'wrong-private-mode');

    $this->artisan('ndi:inbox:sync')
        ->expectsOutputToContain('Invalid NDI catalogue mode')
        ->doesntExpectOutputToContain('wrong-private-mode')
        ->assertFailed();
    expect(app(SystemHealth::class)->report()['ndi_catalog_mode'])->toBe('invalid')
        ->and(app(SystemHealth::class)->healthy())->toBeFalse();
});

test('health explicitly distinguishes paused snapshot from fresh live supplier data', function (): void {
    Storage::fake('local');
    config()->set(['ndi.catalog_currency' => 'NOK', 'ndi.catalog_mode' => 'live']);
    app(NdiBundleImporter::class)->importAvailable([ndiFixture('stock_all_warehouses.xlsx')], 'snapshot-test-setup');
    $this->travel(35)->days();
    config()->set('ndi.catalog_mode', 'snapshot');

    $health = app(SystemHealth::class)->report();
    expect($health['ndi_catalog_mode'])->toBe('snapshot')
        ->and($health['ndi_import_paused'])->toBeTrue()
        ->and($health['ndi_fresh'])->toBeFalse()
        ->and($health['ndi_healthy'])->toBeFalse();
    $this->artisan('easytire:health')
        ->expectsOutputToContain('catalog_mode=snapshot inbox_paused=yes ndi_stock=stale')
        ->assertFailed();
    $this->actingAs(User::factory()->owner()->create())->get('/admin/health')
        ->assertOk()->assertSee('Testkatalog med lagrede data')->assertSee('Automatisk import er satt på pause.');
    $this->actingAs(User::factory()->owner()->create(['locale' => 'ru']))->get('/admin/health')
        ->assertOk()->assertSee('Тестовый каталог из сохранённых данных')->assertSee('Автоматический импорт приостановлен.');
});
