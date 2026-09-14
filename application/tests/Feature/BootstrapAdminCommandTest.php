<?php

use App\Models\User;
use App\Modules\Identity\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('interactive bootstrap creates an active owner without echoing password', function (): void {
    $this->artisan('easytire:bootstrap-admin')
        ->expectsQuestion('Name', 'Owner')
        ->expectsQuestion('Email', ' OWNER@EXAMPLE.TEST ')
        ->expectsQuestion('Password', 'a-safe-password')
        ->expectsQuestion('Confirm password', 'a-safe-password')
        ->expectsConfirmation('Create active owner account for owner@example.test?', 'yes')
        ->expectsOutput('Owner account created.')
        ->doesntExpectOutput('a-safe-password')
        ->assertExitCode(0);

    $this->assertDatabaseHas('users', ['email' => 'owner@example.test', 'role' => 'owner', 'is_active' => true]);
});

test('interactive bootstrap refuses an existing email before asking for a password', function (): void {
    User::factory()->owner()->create(['email' => 'owner@example.test']);

    $this->artisan('easytire:bootstrap-admin')
        ->expectsQuestion('Name', 'Second Owner')
        ->expectsQuestion('Email', 'OWNER@example.test')
        ->expectsOutput('An account with this email already exists.')
        ->assertExitCode(1);

    expect(User::query()->where('email', 'owner@example.test')->count())->toBe(1);
});

test('interactive bootstrap rejects a mismatched password', function (): void {
    $this->artisan('easytire:bootstrap-admin')
        ->expectsQuestion('Name', 'Owner')
        ->expectsQuestion('Email', 'owner@example.test')
        ->expectsQuestion('Password', 'a-safe-password')
        ->expectsQuestion('Confirm password', 'a-different-password')
        ->expectsOutput('Passwords must match and contain at least 12 characters.')
        ->assertExitCode(1);

    $this->assertDatabaseMissing('users', ['email' => 'owner@example.test']);
});

test('interactive bootstrap cannot create a second administrator', function (): void {
    User::factory()->owner()->create(['email' => 'operations@example.test']);

    $this->artisan('easytire:bootstrap-admin')
        ->expectsQuestion('Name', 'Owner')
        ->expectsQuestion('Email', 'owner@example.test')
        ->expectsOutput('An administrator already exists. Create additional accounts through an approved administration workflow.')
        ->assertExitCode(1);

    expect(User::query()->whereIn('role', UserRole::adminValues())->count())->toBe(1);
});
