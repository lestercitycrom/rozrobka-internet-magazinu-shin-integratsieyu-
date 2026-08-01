<?php

use App\Jobs\RecordQueueHeartbeat;
use App\Models\User;
use App\Modules\Operations\SystemHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('health is visible to administrators and hides sensitive storage details', function (): void {
    $this->actingAs(User::factory()->operations()->create())->get('/admin/health')
        ->assertOk()->assertSee('Systemhelse')->assertSee('Database')->assertSee('Køforbindelse')->assertDontSee(base_path());
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
