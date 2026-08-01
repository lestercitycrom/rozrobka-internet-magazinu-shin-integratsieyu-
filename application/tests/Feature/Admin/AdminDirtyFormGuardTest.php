<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin forms expose a localized in-memory dirty guard for locale switches', function (string $locale, string $message): void {
    $admin = User::factory()->operations()->create(['locale' => $locale]);

    $this->actingAs($admin)->get('/admin/content-pages/create')
        ->assertOk()
        ->assertSee('data-admin-dirty-root', false)
        ->assertSee('data-admin-locale-form', false)
        ->assertSee(e($message), false);
})->with([
    'Norwegian Bokmål' => ['nb', 'Du har ulagrede endringer. Vil du bytte språk og forkaste dem?'],
    'Russian' => ['ru', 'Есть несохранённые изменения. Сменить язык и потерять их?'],
]);

test('dirty guard keeps form values in memory only', function (): void {
    $source = file_get_contents(resource_path('js/admin-dirty-guard.js'));

    expect($source)
        ->toContain('window.confirm')
        ->toContain('beforeunload')
        ->not->toContain('localStorage')
        ->not->toContain('sessionStorage')
        ->not->toContain('indexedDB');
});
