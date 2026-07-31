<?php

use App\Modules\VehicleFitment\VehicleLookup;
use Illuminate\Support\Facades\Http;

test('disabled lookup never makes a remote request and normalizes norwegian registration', function () {
    config()->set('vehicle_fitment.enabled', false);
    Http::fake();
    $client = app(VehicleLookup::class);
    expect($client->normalize(' ab 12345 '))->toBe('AB12345')->and($client->lookup('AB12345'))->toBeNull();
    Http::assertNothingSent();
});
test('fitment page honestly falls back to manual catalog selection', function () {
    config()->set('vehicle_fitment.enabled', false);
    $this->get('/biloppslag')->assertOk();
    $this->post('/biloppslag', ['registration' => 'AB12345'])->assertOk()->assertSee('Velg dekk manuelt');
});

test('russian preview localizes fitment and retains its preview SEO guardrails', function (): void {
    config()->set('storefront.ru_preview_enabled', true);

    $this->get('/ru/biloppslag')
        ->assertOk()
        ->assertSee(__('fitment.title', [], 'ru'))
        ->assertDontSee(__('fitment.title', [], 'nb'))
        ->assertSee('lang="ru"', false)
        ->assertSee('noindex,nofollow', false)
        ->assertSee('rel="canonical"', false)
        ->assertSee('/biloppslag', false);
});

test('enabled fake lookup maps approved dimensions without retaining the registration', function () {
    config()->set('vehicle_fitment.enabled', true);
    config()->set('vehicle_fitment.api_key', 'test-key');
    config()->set('vehicle_fitment.endpoint', 'https://example.test/vehicle');
    Http::fake(['https://example.test/vehicle*' => Http::response(['kjoretoydata' => ['merke' => 'Demo', 'handelsbetegnelse' => 'Car', 'godkjenning' => ['tekniskGodkjenning' => ['dekkOgFelg' => [['dekkdimensjon' => ['bredde' => '205', 'profil' => '55', 'diameter' => '16']]]]]]], 200)]);
    $response = $this->post('/biloppslag', ['registration' => 'AB 12345']);
    $response->assertOk()->assertSee('Demo Car')->assertSee('205/55 R16')->assertSee('width=205', false)->assertDontSee('AB 12345');
    Http::assertSent(fn ($request) => $request->hasHeader(config('vehicle_fitment.header'), 'Apikey test-key'));
});
