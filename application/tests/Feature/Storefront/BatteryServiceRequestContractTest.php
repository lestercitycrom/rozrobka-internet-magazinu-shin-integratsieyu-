<?php

use App\Models\User;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Models\ServicePage;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-29 09:00:00', 'Europe/Oslo'));
    foreach ([
        ['slug' => 'bilbatteri', 'title_nb' => 'Bilbatteri i Tromsø', 'title_ru' => 'Автомобильный аккумулятор в Тромсё'],
        ['slug' => 'oljeskift', 'title_nb' => 'Oljeskift', 'title_ru' => 'Замена масла'],
        ['slug' => 'omlegging-balansering', 'title_nb' => 'Omlegging', 'title_ru' => 'Шиномонтаж'],
    ] as $page) {
        ServicePage::query()->create([
            ...$page,
            'sort_order' => 1,
            'publication_status' => ServicePagePublicationStatus::Published,
        ]);
    }
});

afterEach(fn () => CarbonImmutable::setTestNow());

function batteryRequestPayload(array $overrides = []): array
{
    return [
        'submission_token' => (string) Str::uuid(),
        'fulfilment' => 'workshop',
        'registration_number' => 'EV12345',
        'preferred_date' => '2026-09-03',
        'name' => 'Synthetic Battery Buyer',
        'email' => 'battery-buyer@example.test',
        'phone' => '939 58 270',
        'note' => 'Bilen starter tregt om morgenen.',
        'consent' => '1',
        ...$overrides,
    ];
}

test('battery UI marks registration and desired date as required with accessible localized guidance', function (): void {
    $response = $this->get('/tjenester/bilbatteri/foresporsel')->assertOk();
    $html = $response->getContent();

    expect($html)
        ->toMatch('/name="registration_number"[^>]*required/u')
        ->toMatch('/name="preferred_date"[^>]*required/u')
        ->toContain('booking-registration-number-hint')
        ->toContain('booking-preferred-date-hint')
        ->toContain('ikke en bekreftet time');

    config()->set('storefront.ru_preview_enabled', true);
    $this->get('/ru/tjenester/bilbatteri/foresporsel')
        ->assertOk()
        ->assertSee('Укажите госномер')
        ->assertSee('не подтверждённая запись')
        ->assertSee('<meta name="robots" content="noindex,nofollow">', false);
});

test('battery request rejects missing contract fields without persistence or outbox work', function (): void {
    $this->post('/tjenester/bilbatteri/foresporsel', batteryRequestPayload([
        'registration_number' => null,
        'preferred_date' => null,
        'note' => null,
    ]))->assertSessionHasErrors([
        'registration_number' => 'Oppgi registreringsnummeret til bilen.',
        'preferred_date' => 'Velg ønsket dato.',
        'note' => 'Beskriv kort hva du trenger hjelp med.',
    ]);

    expect(ServiceRequest::query()->count())->toBe(0)
        ->and(DB::table('service_request_submissions')->count())->toBe(0)
        ->and(DB::table('outbox_messages')->count())->toBe(0);
});

test('battery desired date rejects invalid and past values', function (): void {
    $this->post('/tjenester/bilbatteri/foresporsel', batteryRequestPayload([
        'preferred_date' => 'not-a-date',
    ]))->assertSessionHasErrors([
        'preferred_date' => 'Oppgi en gyldig ønsket dato.',
    ]);

    $this->post('/tjenester/bilbatteri/foresporsel', batteryRequestPayload([
        'preferred_date' => '2026-08-28',
    ]))->assertSessionHasErrors([
        'preferred_date' => 'Ønsket dato kan ikke være tidligere enn i dag.',
    ]);

    expect(ServiceRequest::query()->count())->toBe(0)
        ->and(DB::table('outbox_messages')->count())->toBe(0);
});

test('valid battery request persists the exact contract once and is visible to administration', function (): void {
    $this->post('/tjenester/bilbatteri/foresporsel', batteryRequestPayload([
        'registration_number' => '  EV 12345  ',
    ]))->assertRedirect();

    $stored = ServiceRequest::query()->sole();
    expect($stored->service_slug)->toBe('bilbatteri')
        ->and($stored->registration_number)->toBe('EV 12345')
        ->and($stored->preferred_date?->format('Y-m-d'))->toBe('2026-09-03')
        ->and($stored->note)->toBe('Bilen starter tregt om morgenen.')
        ->and($stored->name)->toBe('Synthetic Battery Buyer')
        ->and($stored->phone)->toBe('939 58 270')
        ->and($stored->email)->toBe('battery-buyer@example.test');

    $admin = User::factory()->operations()->create();
    $this->actingAs($admin)->get('/admin/service-requests/'.$stored->id)
        ->assertOk()
        ->assertSee('EV 12345')
        ->assertSee('2026-09-03')
        ->assertSee('Bilen starter tregt om morgenen.');
});

test('battery submission token stays idempotent', function (): void {
    $token = (string) Str::uuid();
    $payload = batteryRequestPayload(['submission_token' => $token]);

    $first = $this->post('/tjenester/bilbatteri/foresporsel', $payload)->assertRedirect();
    $second = $this->post('/tjenester/bilbatteri/foresporsel', $payload)->assertRedirect();

    expect($second->headers->get('Location'))->toBe($first->headers->get('Location'))
        ->and(ServiceRequest::query()->count())->toBe(1)
        ->and(ServiceRequest::query()->sole()->submission_token)->toBe($token)
        ->and(DB::table('service_request_submissions')->count())->toBe(1)
        ->and(DB::table('outbox_messages')->count())->toBe(2);
});

test('battery-only requirements do not change generic or mounting contracts', function (): void {
    $generic = batteryRequestPayload([
        'registration_number' => null,
        'preferred_date' => null,
        'note' => null,
    ]);
    $this->post('/tjenester/oljeskift/foresporsel', $generic)->assertRedirect();

    $this->post('/tjenester/omlegging-balansering/foresporsel', [
        ...batteryRequestPayload([
            'registration_number' => null,
            'preferred_date' => null,
            'note' => null,
        ]),
        'service_variant' => 'mounting_balancing',
        'wheel_count' => 4,
        'tire_size' => '205/55 R16',
    ])->assertSessionHasErrors('preferred_date');

    expect(ServiceRequest::query()->count())->toBe(1)
        ->and(ServiceRequest::query()->sole()->service_slug)->toBe('oljeskift');
});

test('battery validation messages use the hidden Russian QA locale', function (): void {
    config()->set('storefront.ru_preview_enabled', true);

    $this->post('/ru/tjenester/bilbatteri/foresporsel', batteryRequestPayload([
        'registration_number' => null,
        'preferred_date' => null,
    ]))->assertSessionHasErrors([
        'registration_number' => 'Укажите госномер автомобиля.',
        'preferred_date' => 'Выберите желаемую дату.',
    ]);

    expect(ServiceRequest::query()->count())->toBe(0)
        ->and(DB::table('outbox_messages')->count())->toBe(0);
});

test('historical battery requests with missing new contract fields remain readable', function (): void {
    $admin = User::factory()->operations()->create();
    $historical = ServiceRequest::query()->create([
        'reference' => 'SR-HISTORICAL-BATTERY',
        'locale' => 'nb',
        'service_slug' => 'bilbatteri',
        'fulfilment' => 'workshop',
        'registration_number' => null,
        'preferred_date' => null,
        'name' => 'Historical Buyer',
        'email' => 'historical@example.test',
        'phone' => '939 58 270',
        'note' => 'Historical record',
        'consent_at' => now(),
    ]);

    $this->actingAs($admin)->get('/admin/service-requests/'.$historical->id)
        ->assertOk()
        ->assertSee('Ikke oppgitt');
});
