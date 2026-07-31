<?php

use App\Models\User;
use App\Modules\Identity\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a guest is redirected from the admin dashboard to the admin login', function (): void {
    $this->get('/admin')->assertRedirect(route('admin.login'));
});

test('an administrator sees operational dashboard links', function (): void {
    $this->actingAs(User::factory()->operations()->create())
        ->get('/admin')
        ->assertOk()
        ->assertSee(route('admin.catalog.index'), false)
        ->assertSee(route('admin.imports.index'), false)
        ->assertSee(route('admin.services.index'), false);
});

test('an active administrator can sign in with a normalized email address', function (): void {
    $admin = User::factory()->owner()->create([
        'email' => 'owner@example.test',
    ]);

    $this->post('/admin/login', [
        'email' => '  OWNER@EXAMPLE.TEST  ',
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($admin);
    $this->assertDatabaseMissing('users', [
        'id' => $admin->id,
        'last_login_at' => null,
    ]);
});

test('wrong credentials, inactive administrators, and customers cannot sign in to the admin', function (): void {
    $inactiveAdmin = User::factory()->owner()->inactive()->create();
    $customer = User::factory()->customer()->create();

    $this->post('/admin/login', [
        'email' => 'nobody@example.test',
        'password' => 'incorrect-password',
    ])->assertSessionHasErrors('email');

    $this->post('/admin/login', [
        'email' => $inactiveAdmin->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->post('/admin/login', [
        'email' => $customer->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('an authenticated customer is denied access to the admin dashboard', function (): void {
    $this->actingAs(User::factory()->customer()->create())
        ->get('/admin')
        ->assertForbidden();
});

test('admin login attempts are throttled by normalized email address and IP address', function (): void {
    $email = 'throttle-'.str()->uuid().'@example.test';

    foreach (range(1, 5) as $_) {
        $this->post('/admin/login', [
            'email' => mb_strtoupper($email),
            'password' => 'incorrect-password',
        ])->assertSessionHasErrors('email');
    }

    $this->post('/admin/login', [
        'email' => $email,
        'password' => 'incorrect-password',
    ])->assertStatus(429);
});

test('logging out invalidates the authenticated admin session', function (): void {
    $this->actingAs(User::factory()->editor()->create())
        ->post('/admin/logout')
        ->assertRedirect(route('admin.login'));

    $this->assertGuest();
});

test('an admin locale is persisted for the user and does not change the public storefront locale', function (): void {
    $admin = User::factory()->operations()->create(['locale' => 'nb']);

    $this->actingAs($admin)
        ->post('/admin/locale', [
            'locale' => 'ru',
            'redirect_to' => '/admin',
        ])
        ->assertRedirect('/admin');

    $this->assertDatabaseHas('users', [
        'id' => $admin->id,
        'locale' => 'ru',
    ]);

    $this->actingAs($admin->fresh())
        ->get('/admin')
        ->assertOk()
        ->assertSee('Панель управления')
        ->assertSee('lang="ru"', false);

    $this->get('/')
        ->assertOk()
        ->assertSee('lang="nb"', false);
});

test('user roles are backed by the identity enum', function (): void {
    $admin = User::factory()->owner()->create();

    expect($admin->role)->toBe(UserRole::Owner)
        ->and($admin->isAdmin())->toBeTrue();
});

test('privileged identity attributes cannot be changed through mass assignment', function (): void {
    $customer = User::factory()->customer()->inactive()->create([
        'last_login_at' => null,
    ]);

    $customer->fill([
        'role' => UserRole::Owner,
        'is_active' => true,
        'last_login_at' => now(),
    ]);

    expect($customer->role)->toBe(UserRole::Customer)
        ->and($customer->is_active)->toBeFalse()
        ->and($customer->last_login_at)->toBeNull();
});
