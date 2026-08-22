<?php

use App\Modules\Cart\Data\CartLine;
use App\Modules\Cart\Data\CartSnapshot;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\ReadModel\CatalogItem;
use App\Modules\Catalog\ReadModel\CatalogQuery;
use App\Modules\Catalog\ReadModel\CatalogReadService;
use App\Modules\Catalog\ReadModel\CatalogStock;
use App\Modules\NdiApi\Contracts\NdiOrderClient;
use App\Modules\NdiApi\Data\NdiC1OrderLine;
use App\Modules\NdiApi\Data\NdiC1OrderRequest;
use App\Modules\NdiApi\Exceptions\NdiApiDisabled;
use App\Modules\NdiApi\NdiIntegrationProfile;
use App\Modules\NdiApi\NdiSandboxCatalogManager;
use App\Modules\Orders\CreatePendingOrder;
use App\Modules\Orders\Data\CheckoutData;
use App\Modules\Orders\Enums\FulfilmentMethod;
use App\Modules\Orders\Exceptions\CartNotReady;
use App\Modules\Pricing\Data\SellingPrice;
use App\Modules\Pricing\SellingPriceService;
use App\Modules\Pricing\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('ndi_api.sandbox.enabled', true);
    config()->set('ndi_api.sandbox.party_id', '1220000');
    config()->set('ndi_api.sandbox.article_id', '4765205551649102');
    config()->set('ndi_api.sandbox.quantity', 2);
    config()->set('ndi_api.sandbox.stock_quantity', 2);
    config()->set('ndi_api.sandbox.price_minor', 1000);
});

test('the sandbox fixture is a searchable priced catalog product and refresh is idempotent', function (): void {
    $this->artisan('ndi:sandbox-catalog')->assertSuccessful();
    $this->artisan('ndi:sandbox-catalog')->assertSuccessful();

    $catalog = app(CatalogReadService::class);
    $page = $catalog->search(new CatalogQuery(
        productType: ProductType::Tire,
        search: '4765205551649102',
        tireWidthMm: '205',
        tireAspectRatio: '55',
        tireRimDiameterIn: '16',
    ));
    $item = $page->items[0];
    $price = app(SellingPriceService::class)->forItem($item);

    expect($page->total)->toBe(1)
        ->and($item->sku)->toBe('NDI-TEST-4765205551649102')
        ->and($item->supplierArticleId)->toBe('4765205551649102')
        ->and($item->integrationProfile)->toBe(NdiIntegrationProfile::SANDBOX)
        ->and($item->isIntegrationTest)->toBeTrue()
        ->and($item->stock->quantity)->toBe(2)
        ->and($price?->money->minor)->toBe(1000)
        ->and($catalog->suggestions(ProductType::Tire, '4765205')[0]['sku'])->toBe('NDI-TEST-4765205551649102')
        ->and($catalog->publicSkuCodes())->not->toContain('NDI-TEST-4765205551649102');

    $this->get(route('storefront.tires.index', ['search' => '4765205551649102']))
        ->assertOk()
        ->assertSee('NDI-TEST-4765205551649102')
        ->assertSee(__('storefront.integration_test_product'))
        ->assertSee('name="quantity" value="2"', false);

    $this->get(route('storefront.products.show', ['sku' => 'NDI-TEST-4765205551649102']))
        ->assertOk()
        ->assertSee('data-testid="integration-test-quantity-note"', false)
        ->assertSee(__('storefront.integration_test_quantity_note', ['quantity' => 2]));
});

test('the fixture can be disabled without deleting its audit records', function (): void {
    $this->artisan('ndi:sandbox-catalog')->assertSuccessful();
    expect(app(CatalogReadService::class)->findPublicSku('NDI-TEST-4765205551649102'))->not->toBeNull();

    $this->artisan('ndi:sandbox-catalog', ['--disable' => true])->assertSuccessful();

    expect(app(CatalogReadService::class)->findPublicSku('NDI-TEST-4765205551649102'))->toBeNull()
        ->and(DB::table('supplier_skus')->where('external_sku', 'NDI-TEST-4765205551649102')->exists())->toBeTrue();
});

test('the sandbox fixture is impossible to activate in production', function (): void {
    $this->app['env'] = 'production';

    expect(fn () => app(NdiSandboxCatalogManager::class)->refresh())
        ->toThrow(LogicException::class, 'forbidden in production');
});

test('sandbox orders freeze the supplier profile and approved NDI article', function (): void {
    $this->artisan('ndi:sandbox-catalog')->assertSuccessful();
    $item = app(CatalogReadService::class)->findPublicSku('NDI-TEST-4765205551649102');
    $price = app(SellingPriceService::class)->forItem($item);
    $cart = new CartSnapshot(
        lines: [new CartLine($item, $price, 2)],
        issues: [],
        total: new Money(2000, 'NOK'),
    );

    $order = app(CreatePendingOrder::class)->execute(new CheckoutData(
        idempotencyKey: (string) Str::uuid(),
        locale: 'nb',
        customerName: 'Sandbox Buyer',
        customerEmail: 'sandbox@example.test',
        customerPhone: '+4700000000',
        fulfilmentMethod: FulfilmentMethod::Pickup,
        vehicleRegistration: null,
        customerNote: null,
    ), $cart)->order->load('items');

    expect($order->integration_profile)->toBe(NdiIntegrationProfile::SANDBOX)
        ->and($order->is_integration_test)->toBeTrue()
        ->and($order->items[0]->external_sku)->toBe('NDI-TEST-4765205551649102')
        ->and($order->items[0]->supplierArticleId())->toBe('4765205551649102');
});

test('sandbox and live products cannot share an order', function (): void {
    $price = new SellingPrice(new Money(1000, 'NOK'), new Money(1000, 'NOK'), 1, 'fixture', 'fixture', now()->toIso8601String());
    $stock = new CatalogStock(4, now()->toIso8601String(), 4, 0);
    $sandbox = new CatalogItem('TEST', ProductType::Tire, 'Test', 'Test', 'Test', [], $stock, [], null, integrationProfile: NdiIntegrationProfile::SANDBOX, isIntegrationTest: true);
    $live = new CatalogItem('LIVE', ProductType::Tire, 'Live', 'Live', 'Live', [], $stock, [], null, integrationProfile: NdiIntegrationProfile::LIVE);
    $cart = new CartSnapshot(
        lines: [new CartLine($sandbox, $price, 2), new CartLine($live, $price, 1)],
        issues: [],
        total: new Money(3000, 'NOK'),
    );

    expect(fn () => app(CreatePendingOrder::class)->execute(new CheckoutData(
        (string) Str::uuid(), 'nb', 'Buyer', 'buyer@example.test', '+4700000000', FulfilmentMethod::Pickup, null, null,
    ), $cart))->toThrow(CartNotReady::class, 'exactly one supplier integration profile');
});

test('sandbox C1 uses only the approved PartyID article and quantity while live C1 stays disabled', function (): void {
    config()->set('ndi_api.endpoint', 'https://ndi.test/EndPoint');
    config()->set('ndi_api.username', 'fixture-user');
    config()->set('ndi_api.password', 'fixture-password');
    config()->set('ndi_api.agency_code', '91');
    config()->set('ndi_api.order_enabled', false);
    config()->set('ndi_api.c1_test_mode', true);
    config()->set('ndi_api.sandbox.order_enabled', true);
    Http::fake(['https://ndi.test/EndPoint' => Http::response(file_get_contents(ndiFixture('c1_order_response_success.xml')), 200)]);

    $request = new NdiC1OrderRequest(
        customerReference: 'EASY-SANDBOX-1',
        lines: [new NdiC1OrderLine('1', '4765205551649102', 2)],
        integrationProfile: NdiIntegrationProfile::SANDBOX,
    );
    $result = app(NdiOrderClient::class)->place($request);

    expect($result->isSuccessful())->toBeTrue();
    Http::assertSent(fn (Request $sent): bool => str_contains($sent->body(), '<PartyID>1220000</PartyID>')
        && str_contains($sent->body(), '<BuyersArticleID>4765205551649102</BuyersArticleID>')
        && str_contains($sent->body(), '<QuantityValue>2</QuantityValue>'));

    expect(fn () => app(NdiOrderClient::class)->place(new NdiC1OrderRequest(
        'EASY-LIVE-1', [new NdiC1OrderLine('1', '4765205551649102', 2)], integrationProfile: NdiIntegrationProfile::LIVE,
    )))->toThrow(NdiApiDisabled::class, 'disabled');
});

test('sandbox C1 refuses every non-approved line before network traffic', function (): void {
    config()->set('ndi_api.endpoint', 'https://ndi.test/EndPoint');
    config()->set('ndi_api.username', 'fixture-user');
    config()->set('ndi_api.password', 'fixture-password');
    config()->set('ndi_api.agency_code', '91');
    config()->set('ndi_api.c1_test_mode', true);
    config()->set('ndi_api.sandbox.order_enabled', true);
    Http::preventStrayRequests();

    expect(fn () => app(NdiOrderClient::class)->place(new NdiC1OrderRequest(
        'EASY-SANDBOX-BAD', [new NdiC1OrderLine('1', 'REAL-SKU', 2)], integrationProfile: NdiIntegrationProfile::SANDBOX,
    )))->toThrow(NdiApiDisabled::class, 'non-approved');
    Http::assertNothingSent();
});
