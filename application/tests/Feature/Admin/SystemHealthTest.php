<?php

use App\Models\User;
use App\Modules\Operations\SystemHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('health is visible to administrators and hides sensitive storage details', function (): void {
    $this->actingAs(User::factory()->operations()->create())->get('/admin/health')
        ->assertOk()->assertSee('Systemhelse')->assertDontSee(base_path());
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
