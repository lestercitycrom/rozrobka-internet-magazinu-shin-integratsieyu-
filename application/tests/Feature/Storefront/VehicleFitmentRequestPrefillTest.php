<?php

use Database\Seeders\SeoServicePageSeeder;
use Database\Seeders\ServicePageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([ServicePageSeeder::class, SeoServicePageSeeder::class]);
});

test('guided complete wheel request is prefilled from the server-side vehicle context', function (): void {
    session(['vehicle_fitment.context' => [
        'intent' => 'packages',
        'registration' => 'AB12345',
        'vehicle' => 'Demo Car',
        'size' => '205/55 R16',
        'source' => 'statens_vegvesen',
        'compatibility_status' => 'manual_confirmation_required',
    ]]);

    $this->get('/tjenester/dekk-felg-og-hjulpakker/foresporsel')
        ->assertOk()
        ->assertSee('data-testid="fitment-request-context"', false)
        ->assertSee('name="vehicle" value="Demo Car"', false)
        ->assertSee('name="registration_number" value="AB12345"', false)
        ->assertSee('name="tire_size" value="205/55 R16"', false);
});

test('guided TPMS request remains in russian preview and is prefilled', function (): void {
    config()->set('storefront.ru_preview_enabled', true);
    session(['vehicle_fitment.context' => [
        'intent' => 'tpms',
        'registration' => 'AB12345',
        'vehicle' => 'Demo Car',
        'size' => '205/55 R16',
        'source' => 'statens_vegvesen',
        'compatibility_status' => 'manual_confirmation_required',
    ]]);

    $this->get('/ru/tjenester/tpms-sensor/foresporsel')
        ->assertOk()
        ->assertSee('lang="ru"', false)
        ->assertSee('data-testid="fitment-request-context"', false)
        ->assertSee('name="registration_number" value="AB12345"', false)
        ->assertSee('name="tire_size" value="205/55 R16"', false);
});
