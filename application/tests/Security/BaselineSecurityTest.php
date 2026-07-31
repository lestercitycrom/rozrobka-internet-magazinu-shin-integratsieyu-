<?php

use Illuminate\Support\Facades\Route;

test('the public home route rejects state-changing HTTP methods', function (): void {
    $this->post('/')->assertStatus(405);
});

test('a production-style exception response does not disclose its message', function (): void {
    config()->set('app.debug', false);

    Route::get('/__test-only/security-exception', static function (): never {
        throw new RuntimeException('test-only secret must not reach the browser');
    });

    $this->withExceptionHandling()
        ->get('/__test-only/security-exception')
        ->assertStatus(500)
        ->assertDontSee('test-only secret must not reach the browser');
});
