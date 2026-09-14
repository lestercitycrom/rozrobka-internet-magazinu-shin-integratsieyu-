<?php

use App\Models\User;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Enums\ServiceRequestFlow;
use App\Modules\Content\Models\ServicePage;
use App\Modules\Outbox\Models\OutboxMessage;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use App\Modules\ServiceBooking\SeasonalQuoteCalculator;
use Database\Seeders\PublicServicePriceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PublicServicePriceSeeder::class);
    ServicePage::create([
        'slug' => 'custom-seasonal',
        'sort_order' => 1,
        'publication_status' => ServicePagePublicationStatus::Published,
        'request_flow' => ServiceRequestFlow::Seasonal,
        'title_nb' => 'Sesongskift',
        'title_ru' => 'Сезонная замена',
    ]);
});

test('seasonal quote adds a new hotel period for stored tyres and supports two cars', function (): void {
    $quote = app(SeasonalQuoteCalculator::class)->calculate('mobile', [
        ['vehicle_type' => 'passenger', 'rim_band' => 'r16-r18', 'storage_plan' => 'renew'],
        ['vehicle_type' => 'passenger', 'rim_band' => 'r19', 'storage_plan' => 'none'],
    ]);

    expect($quote)->toBe(['min_minor' => 230000, 'max_minor' => 230000, 'currency' => 'NOK']);

    $range = app(SeasonalQuoteCalculator::class)->calculate('workshop', [
        ['vehicle_type' => 'passenger', 'rim_band' => 'unknown', 'storage_plan' => 'none'],
    ]);
    expect($range)->toBe(['min_minor' => 55000, 'max_minor' => 65000, 'currency' => 'NOK']);
});

test('seasonal form exposes one or two cars storage renewal and live pricing inputs', function (): void {
    $this->get('/tjenester/custom-seasonal/foresporsel')
        ->assertOk()
        ->assertSee('name="car_count"', false)
        ->assertSee('name="vehicle_2"', false)
        ->assertSee('name="storage_plan"', false)
        ->assertSee('value="renew"', false)
        ->assertSee('data-seasonal-prices=', false)
        ->assertSee('data-seasonal-quote', false)
        ->assertSee('name="preferred_date" type="date"', false)
        ->assertSee('required aria-invalid=', false)
        ->assertSee('Dekkhotell legges alltid til som en ny periode');
});

test('seasonal request validates the second car and stores server calculated quote', function (): void {
    $base = [
        'fulfilment' => 'mobile',
        'preferred_date' => now()->addDay()->format('Y-m-d'),
        'preferred_window' => 'Ettermiddag',
        'car_count' => 2,
        'vehicle' => 'Tesla Model Y',
        'vehicle_type' => 'passenger',
        'rim_band' => 'r16-r18',
        'storage_plan' => 'renew',
        'registration_number' => 'EV12345',
        'name' => 'Buyer',
        'email' => 'buyer@example.test',
        'phone' => '90000000',
        'address' => 'Testvegen 1',
        'consent' => '1',
    ];

    $this->post('/tjenester/custom-seasonal/foresporsel', $base)
        ->assertSessionHasErrors(['vehicle_2', 'vehicle_type_2', 'rim_band_2', 'storage_plan_2']);

    $this->post('/tjenester/custom-seasonal/foresporsel', [
        ...$base,
        'vehicle_2' => 'Volvo XC60',
        'vehicle_type_2' => 'passenger',
        'rim_band_2' => 'r19',
        'storage_plan_2' => 'none',
    ])->assertRedirect();

    $stored = ServiceRequest::query()->latest('id')->firstOrFail();
    expect($stored->vehicles)->toHaveCount(2)
        ->and($stored->vehicles[0]['storage_plan'])->toBe('renew')
        ->and($stored->dekkhotell)->toBeTrue()
        ->and($stored->quote_min_minor)->toBe(230000)
        ->and($stored->quote_max_minor)->toBe(230000)
        ->and($stored->quote_currency)->toBe('NOK')
        ->and(OutboxMessage::query()->count())->toBe(1)
        ->and(OutboxMessage::query()->pluck('type')->all())->toContain('telegram.service_request_created')
        ->and(OutboxMessage::query()->pluck('type')->all())->not->toContain('notion.service_request_created')
        ->and(OutboxMessage::query()->firstOrFail()->getRawOriginal('payload_encrypted'))->not->toContain('buyer@example.test');

    $this->post('/tjenester/custom-seasonal/foresporsel', [
        ...$base,
        'car_count' => 1,
        'fulfilment' => 'workshop',
        'vehicle_type' => 'large_vehicle',
        'address' => null,
    ])->assertSessionHasErrors(['vehicle_type']);
});

test('seasonal public request accepts no email without weakening consent or external routing', function (): void {
    $operations = User::factory()->operations()->create();
    $payload = [
        'fulfilment' => 'workshop',
        'preferred_date' => now()->addDay()->format('Y-m-d'),
        'preferred_window' => 'Ettermiddag',
        'car_count' => 1,
        'vehicle' => 'Tesla Model Y',
        'vehicle_type' => 'passenger',
        'rim_band' => 'r19',
        'storage_plan' => 'none',
        'name' => 'Seasonal no email',
        'phone' => '92222222',
    ];

    $this->post('/tjenester/custom-seasonal/foresporsel', $payload)
        ->assertSessionHasErrors('consent');

    $this->post('/tjenester/custom-seasonal/foresporsel', [...$payload, 'consent' => '1'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $stored = ServiceRequest::query()->sole();
    expect($stored->email)->toBeNull()
        ->and($stored->consent_at)->not->toBeNull()
        ->and($stored->quote_min_minor)->toBe(65000)
        ->and(OutboxMessage::query()->pluck('type')->all())->toBe(['telegram.service_request_created'])
        ->and(OutboxMessage::query()->pluck('type')->all())->not->toContain('notion.service_request_created');

    $this->actingAs($operations)->get('/admin/service-requests?q=Seasonal+no+email')
        ->assertOk()
        ->assertSee($stored->reference)
        ->assertSee('Seasonal no email');
});
