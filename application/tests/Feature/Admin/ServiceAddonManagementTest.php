<?php

use App\Models\User;
use App\Modules\Commerce\Models\ServiceAddon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function serviceAddon(): ServiceAddon
{
    return ServiceAddon::query()->where('code', 'mounting')->firstOrFail();
}

test('administrator can edit a seeded service add-on with a decimal NOK price', function (): void {
    $admin = User::factory()->owner()->create();
    $addon = serviceAddon();

    $this->actingAs($admin)->get('/admin/service-addons')->assertOk()->assertSee('Tilleggstjenester')->assertSee('Pris mangler');
    $this->actingAs($admin)->get('/admin/service-addons/'.$addon->id.'/edit')->assertOk();
    $this->actingAs($admin)->put('/admin/service-addons/'.$addon->id, ['title_nb' => 'Montering', 'title_ru' => 'Монтаж', 'applicability' => 'tire', 'billing_mode' => 'per_item', 'price_nok' => '349,50', 'is_active' => '1', 'sort_order' => '5'])->assertRedirect()->assertSessionHas('status');

    expect($addon->fresh()->price_minor)->toBe(34950)->and($addon->fresh()->currency)->toBe('NOK')->and($addon->fresh()->is_active)->toBeTrue()->and($addon->fresh()->sort_order)->toBe(5);
});

test('administrator cannot activate an unpriced or zero-priced service add-on', function (): void {
    $admin = User::factory()->owner()->create();
    $addon = serviceAddon();
    $payload = ['title_nb' => 'Montering', 'title_ru' => 'Монтаж', 'applicability' => 'tire', 'billing_mode' => 'per_item', 'is_active' => '1', 'sort_order' => '10'];

    $this->actingAs($admin)->put('/admin/service-addons/'.$addon->id, [...$payload, 'price_nok' => ''])->assertSessionHasErrors('price_nok');
    $this->actingAs($admin)->put('/admin/service-addons/'.$addon->id, [...$payload, 'price_nok' => '0.00'])->assertSessionHasErrors('price_nok');
    expect($addon->fresh()->is_active)->toBeFalse();
});

test('service add-on administration has a Russian internal locale', function (): void {
    $admin = User::factory()->owner()->create(['locale' => 'ru']);
    serviceAddon();

    $this->actingAs($admin)->get('/admin/service-addons')->assertOk()->assertSee('Дополнительные услуги')->assertSee('Цена не задана');
});
