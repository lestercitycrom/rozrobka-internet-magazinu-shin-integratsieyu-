<?php

use App\Modules\Cart\CartResolver;
use App\Modules\Cart\SessionCart;
use App\Modules\Commerce\Enums\ServiceAddonApplicability;
use App\Modules\Commerce\Enums\ServiceAddonBillingMode;
use App\Modules\Commerce\Models\ServiceAddon;
use App\Modules\NdiApi\NdiSandboxCatalogManager;
use Database\Seeders\E2eStorefrontSeeder;
use Database\Seeders\ServiceAddonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('production service add-on seed is repeatable inactive and unpriced', function (): void {
    expect(ServiceAddon::query()->count())->toBe(2)
        ->and(ServiceAddon::query()->pluck('code')->all())->toEqualCanonicalizing(['mounting', 'balancing'])
        ->and(ServiceAddon::query()->where('is_active', true)->exists())->toBeFalse()
        ->and(ServiceAddon::query()->whereNotNull('price_minor')->exists())->toBeFalse();

    $this->seed(ServiceAddonSeeder::class);
    $this->seed(ServiceAddonSeeder::class);

    expect(ServiceAddon::query()->count())->toBe(2)
        ->and(ServiceAddon::query()->pluck('code')->all())->toEqualCanonicalizing(['mounting', 'balancing'])
        ->and(ServiceAddon::query()->where('is_active', true)->exists())->toBeFalse()
        ->and(ServiceAddon::query()->whereNotNull('price_minor')->exists())->toBeFalse();
});

test('cart calculates per item and per order services from applicable product quantities', function (): void {
    $this->seed(E2eStorefrontSeeder::class);
    serviceAddonFixture('tire-fit', ServiceAddonApplicability::Tire, ServiceAddonBillingMode::PerItem, 1000);
    serviceAddonFixture('wheel-fit', ServiceAddonApplicability::Wheel, ServiceAddonBillingMode::PerItem, 2000);
    serviceAddonFixture('order-care', ServiceAddonApplicability::All, ServiceAddonBillingMode::PerOrder, 3000);

    $cart = app(SessionCart::class);
    $cart->add('000205550016', 2);
    $cart->add('DEMO-WHEEL-18112', 3);
    $cart->addServiceAddon('tire-fit');
    $cart->addServiceAddon('wheel-fit');
    $cart->addServiceAddon('order-care');

    $snapshot = app(CartResolver::class)->resolve();
    $services = collect($snapshot->serviceLines)->keyBy('code');

    expect($services->get('tire-fit')->quantity)->toBe(2)
        ->and($services->get('wheel-fit')->quantity)->toBe(3)
        ->and($services->get('order-care')->quantity)->toBe(1)
        ->and($snapshot->productsTotal()->minor)->toBe(959500)
        ->and($snapshot->servicesTotal()->minor)->toBe(11000)
        ->and($snapshot->total->minor)->toBe(970500)
        ->and($snapshot->isCheckoutReady())->toBeTrue();
});

test('cart exposes only active priced applicable services in Norwegian and Russian', function (): void {
    $this->seed(E2eStorefrontSeeder::class);
    serviceAddonFixture('visible-care', ServiceAddonApplicability::Tire, ServiceAddonBillingMode::PerOrder, 2500, 'Synlig tjeneste', 'Доступная услуга');
    serviceAddonFixture('wrong-product', ServiceAddonApplicability::Wheel, ServiceAddonBillingMode::PerOrder, 2500, 'Kun felg', 'Только диски');
    serviceAddonFixture('unpriced-care', ServiceAddonApplicability::Tire, ServiceAddonBillingMode::PerOrder, null, 'Uten pris', 'Без цены');

    $session = ['storefront.cart.lines' => ['000205550016' => 1]];
    $this->withSession($session)
        ->get('/handlekurv')
        ->assertOk()
        ->assertSee('Synlig tjeneste')
        ->assertDontSee('Kun felg')
        ->assertDontSee('Uten pris')
        ->assertDontSee('data-service-code="mounting"', false);

    config()->set('storefront.ru_preview_enabled', true);
    $this->get('/ru/handlekurv')
        ->assertOk()
        ->assertSee('Доступная услуга')
        ->assertDontSee('Только диски')
        ->assertDontSee('Без цены');
});

test('unavailable or no longer applicable selected service blocks checkout and can be removed', function (): void {
    $this->seed(E2eStorefrontSeeder::class);
    $addon = serviceAddonFixture('changing-care', ServiceAddonApplicability::Tire, ServiceAddonBillingMode::PerItem, 1500);
    $cart = app(SessionCart::class);
    $cart->add('000205550016', 1);
    $cart->addServiceAddon($addon->code);
    $addon->update(['is_active' => false]);

    $snapshot = app(CartResolver::class)->resolve();
    expect($snapshot->serviceIssues)->toHaveCount(1)
        ->and($snapshot->serviceIssues[0]->reason)->toBe('unavailable')
        ->and($snapshot->isCheckoutReady())->toBeFalse();

    $this->get('/handlekurv')
        ->assertOk()
        ->assertSee('data-testid="cart-service-issue"', false)
        ->assertDontSee('href="'.route('storefront.checkout.create').'"', false);

    $this->delete('/handlekurv/tjenester', ['code' => $addon->code])->assertRedirect();
    expect(app(SessionCart::class)->serviceAddons())->toBe([]);
});

test('selected service becomes a blocking issue when the matching product type is removed', function (): void {
    $this->seed(E2eStorefrontSeeder::class);
    $cart = app(SessionCart::class);
    $cart->add('000205550016', 1);
    $cart->addServiceAddon('e2e-wheel-care');

    $snapshot = app(CartResolver::class)->resolve();

    expect($snapshot->serviceLines)->toBe([])
        ->and($snapshot->serviceIssues)->toHaveCount(1)
        ->and($snapshot->serviceIssues[0]->reason)->toBe('not_applicable')
        ->and($snapshot->isCheckoutReady())->toBeFalse();
});

test('service selection routes validate and toggle the session independently from product lines', function (): void {
    $this->post('/handlekurv/tjenester', ['code' => '<bad>'])->assertSessionHasErrors('code');
    $this->post('/handlekurv/tjenester', ['code' => 'balancing'])->assertRedirect();

    expect(app(SessionCart::class)->serviceAddons())->toBe(['balancing']);

    $this->delete('/handlekurv/tjenester', ['code' => 'balancing'])->assertRedirect();
    expect(app(SessionCart::class)->serviceAddons())->toBe([]);
});

test('the isolated sandbox product cannot expose or persist service add-ons', function (): void {
    config()->set([
        'ndi_api.sandbox.enabled' => true,
        'ndi_api.sandbox.party_id' => '1220000',
        'ndi_api.sandbox.article_id' => '4765205551649102',
        'ndi_api.sandbox.quantity' => 2,
        'ndi_api.sandbox.price_minor' => 1000,
    ]);
    app(NdiSandboxCatalogManager::class)->refresh();
    $addon = serviceAddonFixture('sandbox-forbidden', ServiceAddonApplicability::Tire, ServiceAddonBillingMode::PerOrder, 2500);
    $cart = app(SessionCart::class);
    $cart->add('NDI-TEST-4765205551649102', 2);

    $clean = app(CartResolver::class)->resolve();
    expect($clean->serviceOptions)->toBe([])
        ->and($clean->isCheckoutReady())->toBeTrue();

    $this->from('/handlekurv')->post('/handlekurv/tjenester', ['code' => $addon->code])
        ->assertRedirect('/handlekurv')
        ->assertSessionHas('cart_error', __('commerce.sandbox_services_forbidden'));
    expect($cart->serviceAddons())->toBe([]);

    $cart->addServiceAddon($addon->code);
    $blocked = app(CartResolver::class)->resolve();
    expect($blocked->serviceLines)->toBe([])
        ->and($blocked->serviceOptions)->toBe([])
        ->and($blocked->serviceIssues)->toHaveCount(1)
        ->and($blocked->serviceIssues[0]->reason)->toBe('integration_test_forbidden')
        ->and($blocked->isCheckoutReady())->toBeFalse();
});

function serviceAddonFixture(
    string $code,
    ServiceAddonApplicability $applicability,
    ServiceAddonBillingMode $billingMode,
    ?int $priceMinor,
    string $titleNb = 'Testtjeneste',
    string $titleRu = 'Тестовая услуга',
): ServiceAddon {
    return ServiceAddon::create([
        'code' => $code,
        'title_nb' => $titleNb,
        'title_ru' => $titleRu,
        'applicability' => $applicability,
        'billing_mode' => $billingMode,
        'currency' => 'NOK',
        'price_minor' => $priceMinor,
        'is_active' => true,
        'sort_order' => 100,
    ]);
}
