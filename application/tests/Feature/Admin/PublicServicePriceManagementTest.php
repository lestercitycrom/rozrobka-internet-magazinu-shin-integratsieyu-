<?php

use App\Models\User;
use App\Modules\Commerce\Models\PublicServicePrice;
use Database\Seeders\PublicServicePriceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('public price seed publishes the current price groups in Norwegian and Russian QA', function (): void {
    $this->seed(PublicServicePriceSeeder::class);

    expect(PublicServicePrice::query()->published()->count())->toBe(11);
    $this->get('/priser')->assertOk()->assertSee('Dekkskift på verksted')->assertSee('550 NOK')->assertSee('data-testid="public-price-row"', false);

    config()->set('storefront.ru_preview_enabled', true);
    $this->get('/ru/priser')->assertOk()->assertSee('Замена колёс в мастерской')->assertSee('550 NOK');
});

test('administrator can create edit hide and delete a public service price', function (): void {
    $admin = User::factory()->owner()->create();
    $payload = [
        'code' => 'custom-service',
        'category_code' => 'custom',
        'category_title_nb' => 'Annet',
        'category_title_ru' => 'Другое',
        'title_nb' => 'Kontroll',
        'title_ru' => 'Проверка',
        'description_nb' => 'Etter avtale',
        'description_ru' => 'По договорённости',
        'price_nok' => '499,50',
        'is_active' => '1',
        'sort_order' => '10',
    ];

    $this->actingAs($admin)->get('/admin/public-service-prices')->assertOk()->assertSee('Tjenestepriser');
    $this->actingAs($admin)->post('/admin/public-service-prices', $payload)->assertRedirect()->assertSessionHas('status');
    $price = PublicServicePrice::query()->where('code', 'custom-service')->firstOrFail();
    expect($price->price_minor)->toBe(49950)->and($price->is_active)->toBeTrue();

    $this->actingAs($admin)->put('/admin/public-service-prices/'.$price->id, [...$payload, 'price_nok' => '525', 'is_active' => null])->assertRedirect();
    expect($price->fresh()->price_minor)->toBe(52500)->and($price->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->delete('/admin/public-service-prices/'.$price->id)->assertRedirect();
    expect(PublicServicePrice::query()->count())->toBe(0);
});

test('administrator cannot mark a zero service price as published', function (): void {
    $admin = User::factory()->owner()->create();
    $payload = [
        'code' => 'zero-service',
        'category_code' => 'custom',
        'category_title_nb' => 'Annet',
        'category_title_ru' => 'Другое',
        'title_nb' => 'Kontroll',
        'title_ru' => 'Проверка',
        'price_nok' => '0',
        'is_active' => '1',
        'sort_order' => '10',
    ];

    $this->actingAs($admin)->post('/admin/public-service-prices', $payload)->assertSessionHasErrors('price_nok');
    expect(PublicServicePrice::query()->count())->toBe(0);
});
