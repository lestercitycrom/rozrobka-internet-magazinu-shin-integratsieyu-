<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
