<?php

use App\Modules\VehicleFitment\VehicleLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

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
    $response->assertOk()
        ->assertSee('Demo Car')
        ->assertSee('205/55 R16')
        ->assertSee(__('fitment.intent_legend', [], 'nb'))
        ->assertSee('name="intent" value="tires"', false)
        ->assertSee('name="intent" value="wheels"', false)
        ->assertSee('name="intent" value="packages"', false)
        ->assertSee('name="intent" value="tpms"', false)
        ->assertSee('name="size" value="205|55|16"', false)
        ->assertSee('data-testid="fitment-confirm-size"', false)
        ->assertSee('6.5Jx16')
        ->assertSee('ET45')
        ->assertSee('data-testid="fitment-wheel-sizes"', false)
        ->assertDontSee('AB 12345');
    $response->assertSessionHas('vehicle_fitment.pending');
    $this->post('/biloppslag/bekreft', ['intent' => 'tires', 'size' => '205|55|16'])
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

test('normalized fitment cache survives a second request without serializing the DTO', function (): void {
    config()->set('vehicle_fitment.enabled', true);
    config()->set('vehicle_fitment.api_key', 'test-key');
    config()->set('vehicle_fitment.endpoint', 'https://example.test/vehicle');
    Http::fake(['https://example.test/vehicle*' => Http::response(currentStatensVehiclePayload(), 200)]);

    $lookup = app(VehicleLookup::class);
    $first = $lookup->lookup('GH12345');
    $second = $lookup->lookup('GH12345');
    $cacheKey = 'fitment:v2:'.hash('sha256', 'GH12345');

    expect($first)->not->toBeNull()
        ->and($second)->not->toBeNull()
        ->and($second?->tyres)->toBe($first?->tyres)
        ->and(Cache::get($cacheKey))->toBeArray()
        ->and(Cache::get($cacheKey))->toMatchArray([
            'make' => 'Demo',
            'model' => 'Car',
            'tyres' => [['width' => '205', 'aspect' => '55', 'rim' => '16']],
        ]);

    Http::assertSentCount(1);
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

    $this->post('/biloppslag/bekreft', ['intent' => 'tires', 'size' => '225|45|18'])
        ->assertStatus(422)
        ->assertSessionMissing('vehicle_fitment.confirmed');
});

test('fitment confirmation validates the requested product path', function (): void {
    session(['vehicle_fitment.pending' => [
        'registration' => 'AB12345',
        'source' => 'statens_vegvesen',
        'vehicle' => ['make' => 'Demo', 'model' => 'Car'],
        'tyres' => [['width' => '205', 'aspect' => '55', 'rim' => '16']],
        'wheels' => [['dimension' => '6.5Jx16', 'offset' => 'ET45']],
    ]]);

    $this->post('/biloppslag/bekreft', ['intent' => 'engine', 'size' => '205|55|16'])
        ->assertSessionHasErrors(['intent']);
    $this->post('/biloppslag/bekreft', ['intent' => 'wheels', 'size' => '205|55|99'])
        ->assertStatus(422);
});

test('wheel path preserves vehicle context but never claims automatic compatibility', function (): void {
    session(['vehicle_fitment.pending' => [
        'registration' => 'AB12345',
        'source' => 'statens_vegvesen',
        'vehicle' => ['make' => 'Demo', 'model' => 'Car'],
        'tyres' => [['width' => '205', 'aspect' => '55', 'rim' => '16']],
        'wheels' => [['dimension' => '6.5Jx16', 'offset' => 'ET45']],
    ]]);

    $this->post('/biloppslag/bekreft', ['intent' => 'wheels', 'size' => '205|55|16'])
        ->assertRedirect('/felger')
        ->assertSessionMissing('vehicle_fitment.confirmed')
        ->assertSessionHas('vehicle_fitment.context', fn (array $context): bool => $context['intent'] === 'wheels'
            && $context['vehicle'] === 'Demo Car'
            && $context['compatibility_status'] === 'manual_confirmation_required'
        );

    $this->get('/felger')
        ->assertOk()
        ->assertSee('data-testid="fitment-context-notice"', false)
        ->assertSee(__('fitment.wheels_manual_notice', ['vehicle' => 'Demo Car', 'size' => '205/55 R16'], 'nb'));
});

test('complete wheel and TPMS paths route to an enquiry with trusted vehicle context', function (string $intent, string $path): void {
    session(['vehicle_fitment.pending' => [
        'registration' => 'AB12345',
        'source' => 'statens_vegvesen',
        'vehicle' => ['make' => 'Demo', 'model' => 'Car'],
        'tyres' => [['width' => '205', 'aspect' => '55', 'rim' => '16']],
        'wheels' => [['dimension' => '6.5Jx16', 'offset' => 'ET45']],
    ]]);

    $this->post('/biloppslag/bekreft', ['intent' => $intent, 'size' => '205|55|16'])
        ->assertRedirect($path)
        ->assertSessionMissing('vehicle_fitment.confirmed')
        ->assertSessionHas('vehicle_fitment.context', fn (array $context): bool => $context['intent'] === $intent
            && $context['registration'] === 'AB12345'
            && $context['size'] === '205/55 R16'
        );
})->with([
    'complete wheels' => ['packages', '/tjenester/dekk-felg-og-hjulpakker/foresporsel'],
    'TPMS' => ['tpms', '/tjenester/tpms-sensor/foresporsel'],
]);

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
    $this->post('/ru/biloppslag/bekreft', ['intent' => 'tires', 'size' => '205|55|16'])
        ->assertRedirect('/ru/dekk?width=205&aspect=55&rim=16');
});
