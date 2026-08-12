<?php

use App\Modules\VehicleFitment\VehicleLookup;
use Illuminate\Support\Facades\Http;

/** @param list<string> $dimensions */
function currentStatensVehiclePayload(array $dimensions = ['205/55 R16']): array
{
    return [
        'kjoretoydataListe' => [[
            'godkjenning' => [
                'tekniskGodkjenning' => [
                    'tekniskeData' => [
                        'generelt' => [
                            'merke' => [['merke' => 'Demo', 'merkeKode' => '999']],
                            'handelsbetegnelse' => ['Car'],
                        ],
                        'dekkOgFelg' => [
                            'akselDekkOgFelgKombinasjon' => [[
                                'akselDekkOgFelg' => array_map(
                                    fn (string $dimension, int $index): array => [
                                        'akselId' => $index + 1,
                                        'dekkdimensjon' => $dimension,
                                        'felgdimensjon' => $index === 0 ? '6.5Jx16' : '7.5Jx17',
                                        'innpress' => $index === 0 ? 'ET45' : 'ET48',
                                    ],
                                    $dimensions,
                                    array_keys($dimensions),
                                ),
                            ]],
                        ],
                    ],
                ],
            ],
        ]],
    ];
}

test('disabled lookup never makes a remote request and normalizes norwegian registration', function () {
    config()->set('vehicle_fitment.enabled', false);
    Http::fake();
    $client = app(VehicleLookup::class);
    expect($client->normalize(' ab 12345 '))->toBe('AB12345')->and($client->lookup('AB12345'))->toBeNull();
    Http::assertNothingSent();
});
test('fitment page honestly falls back to manual catalog selection', function () {
    config()->set('vehicle_fitment.enabled', false);
    $this->get('/biloppslag')
        ->assertOk()
        ->assertSee('for="registration"', false)
        ->assertSee('id="registration"', false)
        ->assertSee('type="text"', false)
        ->assertSee('aria-describedby="registration-help"', false)
        ->assertSee('class="et-btn et-btn--primary"', false)
        ->assertSee('type="submit"', false);
    $this->post('/biloppslag', ['registration' => 'AB12345'])->assertOk()->assertSee('Velg dekk manuelt');
});

test('russian preview localizes fitment and retains its preview SEO guardrails', function (): void {
    config()->set('storefront.ru_preview_enabled', true);

    $this->get('/ru/biloppslag')
        ->assertOk()
        ->assertSee(__('fitment.title', [], 'ru'))
        ->assertDontSee(__('fitment.title', [], 'nb'))
        ->assertSee('for="registration"', false)
        ->assertSee('id="registration"', false)
        ->assertSee('type="text"', false)
        ->assertSee('lang="ru"', false)
        ->assertSee('noindex,nofollow', false)
        ->assertSee('rel="canonical"', false)
        ->assertSee('/biloppslag', false);
});

test('enabled fake lookup maps approved dimensions without retaining the registration', function () {
    config()->set('vehicle_fitment.enabled', true);
    config()->set('vehicle_fitment.api_key', 'test-key');
    config()->set('vehicle_fitment.endpoint', 'https://example.test/vehicle');
    Http::fake(['https://example.test/vehicle*' => Http::response(currentStatensVehiclePayload(), 200)]);
    $response = $this->post('/biloppslag', ['registration' => 'AB 12345']);
    $response->assertOk()->assertSee('Demo Car')->assertSee('205/55 R16')->assertSee(__('fitment.confirm_size', [], 'nb'))->assertSee('data-testid="fitment-confirm-size"', false)->assertSee('name="width" value="205"', false)->assertSee('6.5Jx16')->assertSee('ET45')->assertSee('data-testid="fitment-wheel-sizes"', false)->assertDontSee('AB 12345');
    $response->assertSessionHas('vehicle_fitment.pending');
    $this->post('/biloppslag/bekreft', ['width' => '205', 'aspect' => '55', 'rim' => '16'])
        ->assertRedirect('/dekk?width=205&aspect=55&rim=16')
        ->assertSessionHas('vehicle_fitment.confirmed', fn (array $fitment): bool => $fitment['registration'] === 'AB12345' && $fitment['source'] === 'statens_vegvesen');
    Http::assertSent(fn ($request) => $request->hasHeader(config('vehicle_fitment.header'), 'Apikey test-key')
        && str_contains($request->url(), 'kjennemerke=AB12345'));
});

test('current Statens response maps unique safe metric sizes across approved axles', function () {
    config()->set('vehicle_fitment.enabled', true);
    config()->set('vehicle_fitment.api_key', 'test-key');
    config()->set('vehicle_fitment.endpoint', 'https://example.test/vehicle');
    Http::fake(['https://example.test/vehicle*' => Http::response(currentStatensVehiclePayload([
        '205/55R16 91V',
        '205/55 R16',
        '225/45 ZR17 94Y',
        '31X10.50R15',
        '999/10 R99',
    ]), 200)]);

    $fitment = app(VehicleLookup::class)->lookup('CD12345');

    expect($fitment)->not->toBeNull()
        ->and($fitment->make)->toBe('Demo')
        ->and($fitment->model)->toBe('Car')
        ->and($fitment->tyres)->toBe([
            ['width' => '205', 'aspect' => '55', 'rim' => '16'],
            ['width' => '225', 'aspect' => '45', 'rim' => '17'],
        ])
        ->and($fitment->wheels)->toBe([
            ['dimension' => '6.5Jx16', 'offset' => 'ET45'],
            ['dimension' => '7.5Jx17', 'offset' => 'ET48'],
        ]);
});

test('unknown or obsolete response shapes fail closed instead of inventing fitment', function () {
    config()->set('vehicle_fitment.enabled', true);
    config()->set('vehicle_fitment.api_key', 'test-key');
    config()->set('vehicle_fitment.endpoint', 'https://example.test/vehicle');
    Http::fake(['https://example.test/vehicle*' => Http::response([
        'kjoretoydata' => [
            'merke' => 'Legacy',
            'godkjenning' => ['tekniskGodkjenning' => ['dekkOgFelg' => []]],
        ],
    ], 200)]);

    expect(app(VehicleLookup::class)->lookup('EF12345'))->toBeNull();
});

test('a size that was not returned by Statens cannot be confirmed', function (): void {
    session(['vehicle_fitment.pending' => [
        'registration' => 'AB12345',
        'source' => 'statens_vegvesen',
        'tyres' => [['width' => '205', 'aspect' => '55', 'rim' => '16']],
    ]]);

    $this->post('/biloppslag/bekreft', ['width' => '225', 'aspect' => '45', 'rim' => '18'])
        ->assertStatus(422)
        ->assertSessionMissing('vehicle_fitment.confirmed');
});

test('russian fitment results keep buyers inside the private preview', function (): void {
    config()->set('storefront.ru_preview_enabled', true);
    config()->set('vehicle_fitment.enabled', true);
    config()->set('vehicle_fitment.api_key', 'test-key');
    config()->set('vehicle_fitment.endpoint', 'https://example.test/vehicle');
    Http::fake(['https://example.test/vehicle*' => Http::response(currentStatensVehiclePayload(), 200)]);

    $this->post('/ru/biloppslag', ['registration' => 'AB12345'])
        ->assertOk()
        ->assertSee('Demo Car')
        ->assertSee('/ru/biloppslag/bekreft', false)
        ->assertSee('noindex,nofollow', false);
    $this->post('/ru/biloppslag/bekreft', ['width' => '205', 'aspect' => '55', 'rim' => '16'])
        ->assertRedirect('/ru/dekk?width=205&aspect=55&rim=16');
});
