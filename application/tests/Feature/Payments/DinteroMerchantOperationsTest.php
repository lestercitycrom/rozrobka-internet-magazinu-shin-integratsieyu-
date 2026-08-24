<?php

use App\Models\User;
use App\Modules\Orders\Enums\FulfilmentMethod;
use App\Modules\Orders\Enums\NdiOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\CaptureAuthorizedPayment;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Enums\PaymentOperationStatus;
use App\Modules\Payments\Enums\PaymentOperationType;
use App\Modules\Payments\Exceptions\PaymentOperationRejected;
use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Payments\Models\PaymentOperation;
use App\Modules\Payments\RefundCapturedPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\FakePaymentGateway;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set(['payments.enabled' => true, 'payments.provider' => 'fake']);
    $this->gateway = new FakePaymentGateway;
    app()->instance(PaymentGateway::class, $this->gateway);
});

test('partial capture requires documented customer agreement and releases the remaining authorization', function (): void {
    [$order, $attempt] = merchantOperationOrder();
    $actor = User::factory()->operations()->create();
    $lineId = 'product-'.$order->items->first()->id;

    expect(fn () => app(CaptureAuthorizedPayment::class)->execute(
        $order,
        (string) Str::uuid(),
        [$lineId => 1],
        $actor->id,
        'Kunden ønsker bare ett dekk.',
        false,
    ))->toThrow(PaymentOperationRejected::class);

    $key = (string) Str::uuid();
    $result = app(CaptureAuthorizedPayment::class)->execute(
        $order,
        $key,
        [$lineId => 1],
        $actor->id,
        'Kunden ønsker bare ett dekk.',
        true,
    );

    $operation = PaymentOperation::query()->sole();
    expect($result->status)->toBe(PaymentStatus::PartiallyPaid)
        ->and($result->captured_amount_minor)->toBe(10000)
        ->and($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->payment_status)->toBe(PaymentStatus::PartiallyPaid)
        ->and($this->gateway->captureCalls)->toHaveCount(1)
        ->and($this->gateway->captureCalls[0]->amountMinor)->toBe(10000)
        ->and($this->gateway->captureCalls[0]->finalCapture)->toBeTrue()
        ->and($operation->type)->toBe(PaymentOperationType::Capture)
        ->and($operation->status)->toBe(PaymentOperationStatus::Completed)
        ->and($operation->actor_user_id)->toBe($actor->id)
        ->and($operation->note)->toBe('Kunden ønsker bare ett dekk.');
});

test('expired authorization cannot be captured', function (): void {
    [$order, $attempt] = merchantOperationOrder();
    $attempt->update(['authorization_expires_at' => now()->subMinute()]);

    expect(fn () => app(CaptureAuthorizedPayment::class)->execute($order, (string) Str::uuid()))
        ->toThrow(PaymentOperationRejected::class);

    expect($attempt->refresh()->status)->toBe(PaymentStatus::Authorized)
        ->and($this->gateway->captureCalls)->toBeEmpty()
        ->and(PaymentOperation::query()->count())->toBe(0);
});

test('captured lines can be partially and then fully refunded with idempotent audited operations', function (): void {
    [$order] = merchantOperationOrder();
    $actor = User::factory()->operations()->create();
    $lineId = 'product-'.$order->items->first()->id;
    app(CaptureAuthorizedPayment::class)->execute(
        $order,
        (string) Str::uuid(),
        null,
        $actor->id,
        'Varene er klare.',
    );

    $firstKey = (string) Str::uuid();
    $first = app(RefundCapturedPayment::class)->execute(
        $order->refresh(),
        $firstKey,
        [$lineId => 1],
        $actor->id,
        'Ett dekk returnert.',
    );
    $same = app(RefundCapturedPayment::class)->execute(
        $order->refresh(),
        $firstKey,
        [$lineId => 1],
        $actor->id,
        'Ett dekk returnert.',
    );

    expect($first->status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and($same->status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and($first->refunded_amount_minor)->toBe(10000)
        ->and($this->gateway->refundCalls)->toHaveCount(1);

    $second = app(RefundCapturedPayment::class)->execute(
        $order->refresh(),
        (string) Str::uuid(),
        [$lineId => 1],
        $actor->id,
        'Siste dekk returnert.',
    );

    expect($second->status)->toBe(PaymentStatus::Refunded)
        ->and($second->refunded_amount_minor)->toBe(20000)
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($order->status)->toBe(OrderStatus::Refunded)
        ->and($this->gateway->refundCalls)->toHaveCount(2)
        ->and(PaymentOperation::query()->where('type', PaymentOperationType::Refund)->where('status', PaymentOperationStatus::Completed)->count())->toBe(2);
});

/** @return array{Order, PaymentAttempt} */
function merchantOperationOrder(): array
{
    $order = Order::create([
        'public_reference' => 'ET-'.Str::upper(Str::random(14)),
        'idempotency_key' => (string) Str::uuid(),
        'locale' => 'nb',
        'status' => OrderStatus::AwaitingPayment,
        'payment_status' => PaymentStatus::Authorized,
        'ndi_status' => NdiOrderStatus::Accepted,
        'currency' => 'NOK',
        'subtotal_minor' => 20000,
        'total_minor' => 20000,
        'fulfilment_method' => FulfilmentMethod::Pickup,
        'customer_name' => 'Merchant operation test',
        'customer_email' => 'merchant-operation@example.test',
        'customer_phone' => '+4790000000',
        'terms_accepted_at' => now(),
        'privacy_accepted_at' => now(),
        'pricing_snapshot_at' => now(),
        'submitted_at' => now(),
        'goods_ready_at' => now(),
    ]);
    $order->items()->create([
        'external_sku' => 'MERCHANT-OPS-SKU',
        'product_title' => 'Merchant operation tire',
        'quantity' => 2,
        'unit_price_minor' => 10000,
        'line_total_minor' => 20000,
        'currency' => 'NOK',
        'product_snapshot' => ['type' => 'tire'],
        'pricing_snapshot' => ['source' => 'test'],
    ]);
    $attempt = PaymentAttempt::create([
        'order_id' => $order->id,
        'provider' => 'fake',
        'idempotency_key' => (string) Str::uuid(),
        'provider_reference' => 'txn-'.Str::lower(Str::random(12)),
        'provider_session_reference' => 'session-'.Str::lower(Str::random(12)),
        'status' => PaymentStatus::Authorized,
        'amount_minor' => 20000,
        'currency' => 'NOK',
        'started_at' => now(),
        'authorized_at' => now(),
        'authorization_expires_at' => now()->addDays(7),
    ]);

    return [$order->refresh(), $attempt];
}
