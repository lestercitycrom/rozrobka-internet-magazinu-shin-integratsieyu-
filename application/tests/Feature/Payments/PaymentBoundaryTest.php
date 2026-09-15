<?php

use App\Modules\NdiApi\NdiIntegrationProfile;
use App\Modules\Orders\Enums\FulfilmentMethod;
use App\Modules\Orders\Enums\NdiOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\TransitionOrder;
use App\Modules\Outbox\Models\OutboxMessage;
use App\Modules\Payments\AuthorizePayment;
use App\Modules\Payments\CaptureAuthorizedPayment;
use App\Modules\Payments\Contracts\OrderPaymentReadiness;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\DisabledPaymentGateway;
use App\Modules\Payments\Exceptions\PaymentOperationRejected;
use App\Modules\Payments\Exceptions\PaymentProviderRejected;
use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Payments\PaymentIdempotencyKey;
use App\Modules\Payments\VoidAuthorizedPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\FakePaymentGateway;

uses(RefreshDatabase::class);

test('authorization and capture are idempotent and capture publishes paid order only after NDI acceptance', function (): void {
    $fake = bindFakePaymentGateway();
    $order = paymentBoundaryOrder();
    addPaymentBoundaryService($order);
    $authorizationKey = (string) Str::uuid();

    $authorized = app(AuthorizePayment::class)->execute($order, $authorizationKey, 'https://shop.example.test/payment/return');
    $sameAuthorization = app(AuthorizePayment::class)->execute($order->refresh(), $authorizationKey, 'https://shop.example.test/payment/return');

    expect($authorized->status)->toBe(PaymentStatus::Authorized)
        ->and($sameAuthorization->id)->toBe($authorized->id)
        ->and($fake->authorizationCalls)->toHaveCount(1)
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::Authorized)
        ->and(PaymentAttempt::query()->count())->toBe(1);

    $order->update(['ndi_status' => NdiOrderStatus::Accepted, 'goods_ready_at' => now()]);
    $captureKey = (string) Str::uuid();
    $captured = app(CaptureAuthorizedPayment::class)->execute($order->refresh(), $captureKey);
    $sameCapture = app(CaptureAuthorizedPayment::class)->execute($order->refresh(), $captureKey);
    $payload = OutboxMessage::query()->firstOrFail()->payload_encrypted;

    expect($captured->status)->toBe(PaymentStatus::Paid)
        ->and($sameCapture->id)->toBe($captured->id)
        ->and($sameCapture->provider_reference)->toBe($authorized->provider_reference)
        ->and($fake->captureCalls)->toHaveCount(1)
        ->and($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($payload['product_item_count'])->toBe(2)
        ->and($payload['service_item_count'])->toBe(2)
        ->and($payload['product_line_count'])->toBe(1)
        ->and($payload['service_line_count'])->toBe(1)
        ->and($payload['line_count'])->toBe(2)
        ->and(OutboxMessage::query()->count())->toBe(2);
});

test('capture is rejected before NDI acceptance and a second authorization cannot be opened', function (): void {
    $fake = bindFakePaymentGateway();
    $order = paymentBoundaryOrder();
    app(AuthorizePayment::class)->execute($order, (string) Str::uuid(), 'https://shop.example.test/payment/return');

    expect(fn () => app(CaptureAuthorizedPayment::class)->execute($order->refresh(), (string) Str::uuid()))
        ->toThrow(PaymentOperationRejected::class, 'NDI')
        ->and($fake->captureCalls)->toHaveCount(0)
        ->and(fn () => app(AuthorizePayment::class)->execute($order->refresh(), (string) Str::uuid(), 'https://shop.example.test/payment/return'))
        ->toThrow(PaymentOperationRejected::class, 'lifecycle')
        ->and(PaymentAttempt::query()->count())->toBe(1);
});

test('ambiguous authorization retries reuse the durable key while a different key is refused', function (): void {
    $fake = bindFakePaymentGateway();
    $fake->failNextAuthorizationTransport = true;
    $order = paymentBoundaryOrder();
    $key = (string) Str::uuid();
    $authorize = app(AuthorizePayment::class);

    expect(fn () => $authorize->execute($order, $key, 'https://shop.example.test/payment/return'))
        ->toThrow(RuntimeException::class, 'ambiguous')
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::Pending)
        ->and(PaymentAttempt::query()->sole()->status)->toBe(PaymentStatus::Pending)
        ->and(fn () => $authorize->execute($order->refresh(), (string) Str::uuid(), 'https://shop.example.test/payment/return'))
        ->toThrow(PaymentOperationRejected::class, 'lifecycle');

    expect(fn () => $authorize->execute($order->refresh(), $key, 'https://shop.example.test/payment/return'))
        ->toThrow(PaymentOperationRejected::class, 'reconciliation')
        ->and($fake->authorizationCalls)->toHaveCount(1)
        ->and(PaymentAttempt::query()->count())->toBe(1);
});

test('ambiguous capture can only retry the same operation and blocks a competing void', function (): void {
    $fake = bindFakePaymentGateway();
    $order = paymentBoundaryOrder();
    app(AuthorizePayment::class)->execute($order, (string) Str::uuid(), 'https://shop.example.test/payment/return');
    $order->update(['ndi_status' => NdiOrderStatus::Accepted, 'goods_ready_at' => now()]);
    $fake->failNextCaptureTransport = true;
    $captureKey = (string) Str::uuid();

    expect(fn () => app(CaptureAuthorizedPayment::class)->execute($order->refresh(), $captureKey))
        ->toThrow(RuntimeException::class, 'ambiguous')
        ->and(PaymentAttempt::query()->sole()->status)->toBe(PaymentStatus::Capturing)
        ->and(fn () => app(VoidAuthorizedPayment::class)->execute($order->refresh(), (string) Str::uuid()))
        ->toThrow(PaymentOperationRejected::class)
        ->and(fn () => app(CaptureAuthorizedPayment::class)->execute($order->refresh(), (string) Str::uuid()))
        ->toThrow(PaymentOperationRejected::class, 'different capture');

    app(CaptureAuthorizedPayment::class)->execute($order->refresh(), $captureKey);
    app(CaptureAuthorizedPayment::class)->execute($order->refresh(), $captureKey);

    expect($fake->captureCalls)->toHaveCount(2)
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::Paid);
});

test('cancelling an authorized payment voids it first and an ambiguous void is retried safely', function (): void {
    $fake = bindFakePaymentGateway();
    $order = paymentBoundaryOrder();
    app(AuthorizePayment::class)->execute($order, (string) Str::uuid(), 'https://shop.example.test/payment/return');
    $fake->failNextVoidTransport = true;

    expect(fn () => app(TransitionOrder::class)->execute($order->refresh(), OrderStatus::Cancelled, note: 'Customer cancelled'))
        ->toThrow(RuntimeException::class, 'ambiguous')
        ->and($order->refresh()->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($order->payment_status)->toBe(PaymentStatus::Authorized)
        ->and(PaymentAttempt::query()->sole()->status)->toBe(PaymentStatus::Voiding);

    $cancelled = app(TransitionOrder::class)->execute($order->refresh(), OrderStatus::Cancelled, note: 'Customer cancelled');

    expect($cancelled->status)->toBe(OrderStatus::Cancelled)
        ->and($cancelled->payment_status)->toBe(PaymentStatus::Voided)
        ->and(PaymentAttempt::query()->sole()->void_idempotency_key)->toBe(PaymentIdempotencyKey::voidFor($order))
        ->and($fake->voidCalls)->toHaveCount(2);
});

test('definitive authorization rejection is terminal and production binding fails before persistence', function (): void {
    $fake = bindFakePaymentGateway();
    $fake->rejectNextAuthorization = true;
    $order = paymentBoundaryOrder();
    $key = (string) Str::uuid();

    expect(fn () => app(AuthorizePayment::class)->execute($order, $key, 'https://shop.example.test/payment/return'))
        ->toThrow(PaymentProviderRejected::class)
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::Failed)
        ->and(PaymentAttempt::query()->sole()->status)->toBe(PaymentStatus::Failed)
        ->and(fn () => app(AuthorizePayment::class)->execute($order->refresh(), $key, 'https://shop.example.test/payment/return'))
        ->toThrow(PaymentOperationRejected::class, 'definitively rejected');

    app()->forgetInstance(PaymentGateway::class);
    app()->bind(PaymentGateway::class, DisabledPaymentGateway::class);
    $second = paymentBoundaryOrder();
    expect(fn () => app(AuthorizePayment::class)->execute($second, (string) Str::uuid(), 'https://shop.example.test/payment/return'))
        ->toThrow(PaymentOperationRejected::class, 'configured payment provider')
        ->and(PaymentAttempt::query()->where('order_id', $second->id)->count())->toBe(0)
        ->and($second->refresh()->payment_status)->toBe(PaymentStatus::NotStarted);
});

test('Dintero test mode rejects non-isolated orders before payment persistence or gateway calls', function (
    bool $ordinaryLiveOrder,
    array $orderOverrides,
    array $itemOverrides,
    bool $addProductLine,
    bool $addServiceLine,
): void {
    configureDinteroTestBoundary();
    $fake = bindFakePaymentGateway();
    $order = $ordinaryLiveOrder
        ? paymentBoundaryOrder()
        : paymentBoundarySandboxOrder($orderOverrides, $itemOverrides);

    if ($addProductLine) {
        $order->items()->create([
            'external_sku' => 'LIVE-EXTRA-SKU',
            'product_title' => 'Unexpected live tire',
            'quantity' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'currency' => 'NOK',
            'product_snapshot' => ['product_type' => 'tire', 'supplier_article_id' => 'LIVE-ARTICLE'],
            'pricing_snapshot' => ['rule_id' => 2],
        ]);
    }
    if ($addServiceLine) {
        $order->serviceItems()->create([
            'service_code' => 'mounting',
            'title_nb' => 'Montering',
            'title_ru' => 'Монтаж',
            'applicability' => 'tire',
            'billing_mode' => 'per_item',
            'quantity' => 2,
            'unit_price_minor' => 100,
            'line_total_minor' => 200,
            'currency' => 'NOK',
            'service_snapshot' => ['code' => 'mounting'],
        ]);
    }
    $order->load(['items', 'serviceItems']);

    expect(fn () => app(AuthorizePayment::class)->execute($order, (string) Str::uuid(), 'https://shop.example.test/payment/return'))
        ->toThrow(PaymentOperationRejected::class, 'approved isolated integration fixture')
        ->and(PaymentAttempt::query()->where('order_id', $order->id)->count())->toBe(0)
        ->and($fake->authorizationCalls)->toHaveCount(0)
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::NotStarted);
})->with([
    'ordinary live order' => [true, [], [], false, false],
    'missing integration-test flag' => [false, ['is_integration_test' => false], [], false, false],
    'wrong integration profile' => [false, ['integration_profile' => 'ndi_live'], [], false, false],
    'mixed live and sandbox products' => [false, [], [], true, false],
    'sandbox product with a service add-on' => [false, [], [], false, true],
    'wrong SKU' => [false, [], ['external_sku' => 'NDI-TEST-WRONG'], false, false],
    'wrong supplier article' => [false, [], ['product_snapshot' => ['product_type' => 'tire', 'supplier_article_id' => 'WRONG-ARTICLE']], false, false],
    'wrong quantity' => [false, [], ['quantity' => 1], false, false],
    'wrong unit amount' => [false, [], ['unit_price_minor' => 999], false, false],
    'wrong line amount' => [false, [], ['line_total_minor' => 1999], false, false],
    'wrong subtotal' => [false, ['subtotal_minor' => 1999], [], false, false],
    'wrong order amount' => [false, ['total_minor' => 1999], [], false, false],
    'wrong currency' => [false, ['currency' => 'SEK'], [], false, false],
]);

test('Dintero test mode authorizes the exact isolated sandbox order through the gateway', function (): void {
    configureDinteroTestBoundary();
    $fake = bindFakePaymentGateway();
    $order = paymentBoundarySandboxOrder();

    $attempt = app(AuthorizePayment::class)->execute($order, (string) Str::uuid(), 'https://shop.example.test/payment/return');

    expect($attempt->status)->toBe(PaymentStatus::Authorized)
        ->and($attempt->amount_minor)->toBe(2000)
        ->and($attempt->currency)->toBe('NOK')
        ->and(PaymentAttempt::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and($fake->authorizationCalls)->toHaveCount(1)
        ->and($fake->authorizationCalls[0]->amountMinor)->toBe(2000)
        ->and($fake->authorizationCalls[0]->currency)->toBe('NOK');
});

test('the sandbox fixture is rejected outside Dintero test mode and ignores mutable price configuration', function (): void {
    configureDinteroTestBoundary();
    $fake = bindFakePaymentGateway();
    $order = paymentBoundarySandboxOrder();

    config()->set('payments.dintero.environment', 'production');
    expect(fn () => app(AuthorizePayment::class)->execute($order, (string) Str::uuid(), 'https://shop.example.test/payment/return'))
        ->toThrow(PaymentOperationRejected::class, 'restricted to Dintero test mode')
        ->and($fake->authorizationCalls)->toHaveCount(0)
        ->and(PaymentAttempt::query()->where('order_id', $order->id)->count())->toBe(0);

    config()->set('payments.dintero.environment', 'test');
    config()->set('ndi_api.sandbox.price_minor', 9999);
    expect(fn () => app(AuthorizePayment::class)->execute($order->refresh(), (string) Str::uuid(), 'https://shop.example.test/payment/return'))
        ->not->toThrow(Throwable::class)
        ->and($fake->authorizationCalls)->toHaveCount(1)
        ->and($fake->authorizationCalls[0]->amountMinor)->toBe(2000);
});

test('Dintero test mode normalization cannot bypass the isolated-order boundary', function (): void {
    configureDinteroTestBoundary();
    config()->set('payments.dintero.environment', ' TEST ');
    $fake = bindFakePaymentGateway();
    $order = paymentBoundaryOrder();

    expect(fn () => app(AuthorizePayment::class)->execute($order, (string) Str::uuid(), 'https://shop.example.test/payment/return'))
        ->toThrow(PaymentOperationRejected::class, 'approved isolated integration fixture')
        ->and($fake->authorizationCalls)->toHaveCount(0)
        ->and(PaymentAttempt::query()->where('order_id', $order->id)->count())->toBe(0);
});

test('Dintero test mode also blocks follow-up gateway operations for prior non-fixture orders', function (): void {
    $fake = bindFakePaymentGateway();
    $order = paymentBoundaryOrder();
    app(AuthorizePayment::class)->execute($order, (string) Str::uuid(), 'https://shop.example.test/payment/return');
    $order->update(['ndi_status' => NdiOrderStatus::Accepted, 'goods_ready_at' => now()]);
    configureDinteroTestBoundary();

    expect(fn () => app(CaptureAuthorizedPayment::class)->execute($order->refresh(), (string) Str::uuid()))
        ->toThrow(PaymentOperationRejected::class, 'approved isolated integration fixture')
        ->and(fn () => app(VoidAuthorizedPayment::class)->execute($order->refresh(), (string) Str::uuid()))
        ->toThrow(PaymentOperationRejected::class, 'approved isolated integration fixture')
        ->and($fake->captureCalls)->toHaveCount(0)
        ->and($fake->voidCalls)->toHaveCount(0);
});

test('an NDI order that may exist cannot be cancelled through the local payment path', function (): void {
    bindFakePaymentGateway();
    $order = paymentBoundaryOrder();
    app(AuthorizePayment::class)->execute($order, (string) Str::uuid(), 'https://shop.example.test/payment/return');
    $order->update(['ndi_status' => NdiOrderStatus::Accepted]);

    expect(fn () => app(TransitionOrder::class)->execute($order->refresh(), OrderStatus::Cancelled, note: 'Unsafe cancel'))
        ->toThrow(InvalidOrderTransition::class, 'supplier review')
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::Authorized);
});

test('a forbidden order transition is rejected before calling payment void', function (): void {
    $fake = bindFakePaymentGateway();
    $order = paymentBoundaryOrder();
    app(AuthorizePayment::class)->execute($order, (string) Str::uuid(), 'https://shop.example.test/payment/return');
    $order->update(['status' => OrderStatus::Fulfilled]);

    expect(fn () => app(TransitionOrder::class)->execute($order->refresh(), OrderStatus::Cancelled, note: 'Invalid stale transition'))
        ->toThrow(InvalidOrderTransition::class, 'not allowed')
        ->and($fake->voidCalls)->toHaveCount(0)
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::Authorized)
        ->and(PaymentAttempt::query()->sole()->status)->toBe(PaymentStatus::Authorized);
});

function bindFakePaymentGateway(): FakePaymentGateway
{
    $fake = new FakePaymentGateway;
    app()->instance(PaymentGateway::class, $fake);
    app()->instance(OrderPaymentReadiness::class, new class implements OrderPaymentReadiness
    {
        public function assertReady(Order $order): void {}
    });

    return $fake;
}

/**
 * @param  array<string, mixed>  $orderOverrides
 * @param  array<string, mixed>  $itemOverrides
 */
function paymentBoundaryOrder(array $orderOverrides = [], array $itemOverrides = []): Order
{
    $order = Order::create(array_replace([
        'public_reference' => 'ET-'.Str::upper(Str::random(14)),
        'idempotency_key' => (string) Str::uuid(),
        'locale' => 'nb',
        'status' => OrderStatus::AwaitingPayment,
        'payment_status' => PaymentStatus::NotStarted,
        'ndi_status' => NdiOrderStatus::NotSubmitted,
        'currency' => 'NOK',
        'subtotal_minor' => 200000,
        'total_minor' => 200000,
        'fulfilment_method' => FulfilmentMethod::Pickup,
        'customer_name' => 'Payment Fixture',
        'customer_email' => 'payment@example.test',
        'customer_phone' => '+47 900 00 000',
        'terms_accepted_at' => now(),
        'privacy_accepted_at' => now(),
        'pricing_snapshot_at' => now(),
        'submitted_at' => now(),
    ], $orderOverrides));
    $order->items()->create(array_replace([
        'external_sku' => 'PAYMENT-SKU',
        'product_title' => 'Payment Fixture Tire',
        'quantity' => 2,
        'unit_price_minor' => 100000,
        'line_total_minor' => 200000,
        'currency' => 'NOK',
        'product_snapshot' => ['product_type' => 'tire'],
        'pricing_snapshot' => ['rule_id' => 1],
    ], $itemOverrides));

    return $order->load('items');
}

/**
 * @param  array<string, mixed>  $orderOverrides
 * @param  array<string, mixed>  $itemOverrides
 */
function paymentBoundarySandboxOrder(array $orderOverrides = [], array $itemOverrides = []): Order
{
    return paymentBoundaryOrder(
        array_replace([
            'integration_profile' => NdiIntegrationProfile::SANDBOX,
            'is_integration_test' => true,
            'currency' => 'NOK',
            'subtotal_minor' => 2000,
            'total_minor' => 2000,
        ], $orderOverrides),
        array_replace([
            'external_sku' => 'NDI-TEST-4765205551649102',
            'quantity' => 2,
            'unit_price_minor' => 1000,
            'line_total_minor' => 2000,
            'currency' => 'NOK',
            'product_snapshot' => [
                'product_type' => 'tire',
                'supplier_article_id' => '4765205551649102',
            ],
        ], $itemOverrides),
    );
}

function configureDinteroTestBoundary(): void
{
    config()->set([
        'payments.enabled' => true,
        'payments.provider' => 'dintero',
        'payments.dintero.environment' => 'test',
        'ndi_api.sandbox.catalog_sku' => 'NDI-TEST-4765205551649102',
        'ndi_api.sandbox.article_id' => '4765205551649102',
        'ndi_api.sandbox.quantity' => 2,
        'ndi_api.sandbox.price_minor' => 1000,
    ]);
}

function addPaymentBoundaryService(Order $order): void
{
    $order->serviceItems()->create([
        'service_code' => 'mounting',
        'title_nb' => 'Montering',
        'title_ru' => 'Монтаж',
        'applicability' => 'tire',
        'billing_mode' => 'per_item',
        'quantity' => 2,
        'unit_price_minor' => 10000,
        'line_total_minor' => 20000,
        'currency' => 'NOK',
        'service_snapshot' => ['code' => 'mounting'],
    ]);
    $order->update(['subtotal_minor' => 220000, 'total_minor' => 220000]);
}
