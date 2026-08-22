<?php

use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Models\ServicePage;
use App\Rules\CustomerPhone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

test('customer phone validation accepts Norwegian and international formats', function (string $phone): void {
    expect(Validator::make(['phone' => $phone], ['phone' => ['required', new CustomerPhone]])->passes())->toBeTrue();
})->with([
    'Norwegian local' => '939 58 270',
    'Norwegian plus prefix' => '+47 939 58 270',
    'Norwegian double-zero prefix' => '0047 939 58 270',
    'international' => '+380 50 123 45 67',
]);

test('customer phone validation rejects incomplete and malformed values', function (string $phone): void {
    expect(Validator::make(['phone' => $phone], ['phone' => ['required', new CustomerPhone]])->fails())->toBeTrue();
})->with([
    'too short' => '123',
    'letters' => 'call-me',
    'bad Norwegian prefix' => '+47 123 45 678',
    'duplicate plus' => '++47 93958270',
]);

test('checkout and service requests reject an invalid customer phone with localized feedback', function (): void {
    $checkoutKey = '11111111-1111-4111-8111-111111111111';
    $this->withSession(['checkout.key' => $checkoutKey])
        ->post('/kasse', [
            'idempotency_key' => $checkoutKey,
            'name' => 'Buyer',
            'email' => 'buyer@example.test',
            'phone' => '123',
            'consent' => '1',
            'terms' => '1',
        ])
        ->assertSessionHasErrors('phone');

    ServicePage::create([
        'slug' => 'phone-test',
        'sort_order' => 1,
        'publication_status' => ServicePagePublicationStatus::Published,
        'title_nb' => 'Telefonkontroll',
        'title_ru' => 'Проверка телефона',
    ]);

    $this->post('/tjenester/phone-test/foresporsel', [
        'fulfilment' => 'workshop',
        'name' => 'Buyer',
        'email' => 'buyer@example.test',
        'phone' => 'abc',
        'consent' => '1',
    ])->assertSessionHasErrors('phone');
});

test('phone fields expose browser validation hints in Norwegian and Russian', function (): void {
    $this->get('/kasse')->assertRedirect('/handlekurv');

    ServicePage::create([
        'slug' => 'phone-ui',
        'sort_order' => 1,
        'publication_status' => ServicePagePublicationStatus::Published,
        'title_nb' => 'Telefon',
        'title_ru' => 'Телефон',
    ]);

    $this->get('/tjenester/phone-ui/foresporsel')
        ->assertOk()
        ->assertSee('type="tel"', false)
        ->assertSee('inputmode="tel"', false)
        ->assertSee('pattern="\+?[0-9][0-9 ()-]{7,23}"', false)
        ->assertSee(__('phone_validation.hint'));

    config()->set('storefront.ru_preview_enabled', true);
    $this->get('/ru/tjenester/phone-ui/foresporsel')
        ->assertOk()
        ->assertSee('Норвежский номер: 8 цифр.');
});
