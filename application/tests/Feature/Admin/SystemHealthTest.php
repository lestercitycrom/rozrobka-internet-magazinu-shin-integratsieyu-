<?php

use App\Jobs\RecordQueueHeartbeat;
use App\Models\User;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Enums\PublicationStatus;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductMediaSource;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Operations\SystemHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('health is visible to administrators and hides sensitive storage details', function (): void {
    $this->actingAs(User::factory()->owner()->create())->get('/admin/health')
        ->assertOk()->assertSee('Systemhelse')->assertSee('Database')->assertSee('Køforbindelse')->assertSee('Dintero-betaling')->assertSee('Notion')->assertDontSee(base_path());
});

test('enabled Dintero is healthy only with environment credentials and protected callbacks', function (): void {
    config()->set([
        'payments.enabled' => true,
        'payments.provider' => 'dintero',
        'payments.dintero.environment' => 'test',
        'payments.dintero.test_account_id' => 'T12345678',
        'payments.dintero.client_id' => 'client',
        'payments.dintero.client_secret' => 'secret',
        'payments.dintero.require_callback_signature' => false,
        'payments.dintero.callback_secret' => null,
    ]);

    expect(app(SystemHealth::class)->report()['payment_configured'])->toBeTrue()
        ->and(app(SystemHealth::class)->report()['payment_callback_protected'])->toBeFalse()
        ->and(app(SystemHealth::class)->healthy())->toBeFalse();

    config()->set([
        'payments.dintero.require_callback_signature' => true,
        'payments.dintero.callback_secret' => 'callback-secret',
    ]);

    expect(app(SystemHealth::class)->report()['payment_callback_protected'])->toBeTrue();
});

test('guests and customers cannot read system health', function (): void {
    $this->get('/admin/health')->assertRedirect(route('admin.login'));
    $this->actingAs(User::factory()->customer()->create())->get('/admin/health')->assertForbidden();
});

test('health command emits only a short redacted summary', function (): void {
    $this->artisan('easytire:health')->expectsOutputToContain('db=')->doesntExpectOutputToContain(base_path());
});

test('health reports a non-negative whole-minute age for the last successful NDI import', function (): void {
    $this->freezeTime();
    $supplierId = DB::table('suppliers')->insertGetId([
        'code' => 'ndi-health',
        'name' => 'NDI Health Test',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('import_runs')->insert([
        'id' => (string) Str::ulid(),
        'supplier_id' => $supplierId,
        'run_type' => 'bundle',
        'status' => 'succeeded',
        'trigger' => 'test',
        'parser_version' => 'test',
        'started_at' => now()->subMinutes(16),
        'finished_at' => now()->subMinutes(15),
        'created_at' => now()->subMinutes(16),
        'updated_at' => now()->subMinutes(15),
    ]);

    expect(app(SystemHealth::class)->report()['ndi_age_minutes'])->toBe(15);
});

test('a duplicate bundle run does not make old NDI data appear fresh', function (): void {
    $this->freezeTime();
    $supplierId = DB::table('suppliers')->insertGetId([
        'code' => 'ndi-health-duplicate', 'name' => 'NDI Duplicate Health', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    foreach ([['succeeded', 120], ['duplicate', 5]] as [$status, $minutes]) {
        DB::table('import_runs')->insert([
            'id' => (string) Str::ulid(), 'supplier_id' => $supplierId, 'run_type' => 'bundle',
            'status' => $status, 'trigger' => 'test', 'parser_version' => 'test',
            'started_at' => now()->subMinutes($minutes + 1), 'finished_at' => now()->subMinutes($minutes),
            'created_at' => now()->subMinutes($minutes + 1), 'updated_at' => now()->subMinutes($minutes),
        ]);
    }

    expect(app(SystemHealth::class)->report()['ndi_age_minutes'])->toBe(120);
});

test('health detects live and stale scheduler and queue workers', function (): void {
    $this->freezeTime();
    config()->set('health.require_heartbeats', true);
    config()->set('health.heartbeat_max_age_minutes', 5);
    config()->set('health.disk_free_warning_bytes', 0);
    Cache::put('easytire.health.scheduler_heartbeat_at', now()->subMinutes(2)->toIso8601String());
    Cache::put('easytire.health.queue_heartbeat_at', now()->subMinutes(6)->toIso8601String());

    $report = app(SystemHealth::class)->report();

    expect($report['scheduler_healthy'])->toBeTrue()
        ->and($report['scheduler_age_minutes'])->toBe(2)
        ->and($report['queue_healthy'])->toBeFalse()
        ->and($report['queue_age_minutes'])->toBe(6)
        ->and(app(SystemHealth::class)->healthy())->toBeFalse();

    (new RecordQueueHeartbeat)->handle();

    expect(app(SystemHealth::class)->report()['queue_healthy'])->toBeTrue()
        ->and(app(SystemHealth::class)->healthy())->toBeTrue();
});

test('health exposes and fails on supplier media download failures', function (): void {
    config()->set('health.disk_free_warning_bytes', 0);
    $supplier = Supplier::create(['code' => 'ndi-health-media', 'name' => 'NDI Health Media', 'is_active' => true]);
    $brand = Brand::create(['canonical_name' => 'Health media', 'slug' => 'health-media']);
    $product = Product::create([
        'product_type' => ProductType::Tire,
        'brand_id' => $brand->id,
        'model_name' => 'Health',
        'title' => 'Health media',
        'publication_status' => PublicationStatus::Draft,
        'first_seen_at' => now(),
    ]);
    $artifactId = (string) Str::ulid();
    DB::table('source_artifacts')->insert([
        'id' => $artifactId, 'supplier_id' => $supplier->id, 'kind' => 'fixture', 'original_filename' => 'health.xlsx',
        'storage_key' => 'fixtures/'.$artifactId.'.xlsx', 'byte_size' => 1, 'sha256' => hash('sha256', $artifactId),
        'received_at' => now(), 'is_redacted' => true,
    ]);
    ProductMediaSource::create([
        'product_id' => $product->id,
        'source_url' => 'https://files.ndias.com/Imagev2.aspx?id=11111111-1111-4111-8111-111111111111',
        'source_artifact_id' => $artifactId,
        'status' => 'failed',
    ]);

    $report = app(SystemHealth::class)->report();

    expect($report['ndi_media_failed'])->toBe(1)
        ->and($report['ndi_media_pending'])->toBe(0)
        ->and(app(SystemHealth::class)->healthy())->toBeFalse();
});
