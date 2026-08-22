<?php

use App\Modules\Orders\Enums\FulfilmentMethod;
use App\Modules\Orders\Enums\NdiOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Outbox\Models\OutboxMessage;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\PaymentTransactionResult;
use App\Modules\Payments\Enums\ProviderPaymentState;
use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Payments\Models\PaymentWebhookEvent;
use App\Modules\Payments\SynchronizeProviderPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\FakePaymentGateway;

uses(RefreshDatabase::class);

test('verified provider states are idempotent and Notion starts only after actual capture', function (): void {
    config()->set(['payments.enabled' => true, 'payments.provider' => 'fake']);
    $gateway = new FakePaymentGateway;
    app()->instance(PaymentGateway::class, $gateway);
    $order = providerSyncOrder();
    PaymentAttempt::create([
        'order_id' => $order->id,
        'provider' => 'fake',
        'idempotency_key' => (string) Str::uuid(),
        'provider_session_reference' => 'session-1',
        'status' => PaymentStatus::Pending,
        'amount_minor' => $order->total_minor,
        'currency' => $order->currency,
        'started_at' => now(),
    ]);

    $gateway->transactions['txn-1'] = providerSyncTransaction($order, ProviderPaymentState::Authorized, 'AUTHORIZED', '2026-08-22T10:00:00Z');
    $authorized = app(SynchronizeProviderPayment::class)->execute($order, 'txn-1', 'test_callback');

    expect($authorized->status)->toBe(PaymentStatus::Authorized)
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::Authorized)
        ->and($order->status)->toBe(OrderStatus::AwaitingPayment)
        ->and(OutboxMessage::query()->count())->toBe(0);

    $gateway->transactions['txn-1'] = providerSyncTransaction($order, ProviderPaymentState::Paid, 'CAPTURED', '2026-08-22T10:01:00Z');
    app(SynchronizeProviderPayment::class)->execute($order, 'txn-1', 'test_callback');
    app(SynchronizeProviderPayment::class)->execute($order, 'txn-1', 'test_callback');

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->status)->toBe(OrderStatus::Paid)
        ->and(OutboxMessage::query()->count())->toBe(2)
        ->and(PaymentWebhookEvent::query()->where('outcome', 'paid_without_goods_ready')->count())->toBe(1);

    $gateway->transactions['txn-1'] = providerSyncTransaction($order, ProviderPaymentState::Authorized, 'AUTHORIZED', '2026-08-22T10:02:00Z');
    app(SynchronizeProviderPayment::class)->execute($order, 'txn-1', 'late_callback');

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and(PaymentAttempt::query()->sole()->status)->toBe(PaymentStatus::Paid)
        ->and(PaymentWebhookEvent::query()->where('outcome', 'ignored_terminal_state')->count())->toBe(1)
        ->and(OutboxMessage::query()->count())->toBe(2);
});

function providerSyncOrder(): Order
{
    $order = Order::create([
        'public_reference' => 'ET-'.Str::upper(Str::random(14)),
        'idempotency_key' => (string) Str::uuid(),
        'locale' => 'nb',
        'status' => OrderStatus::AwaitingPayment,
        'payment_status' => PaymentStatus::Pending,
        'ndi_status' => NdiOrderStatus::NotSubmitted,
        'currency' => 'NOK',
        'subtotal_minor' => 149900,
        'total_minor' => 149900,
        'fulfilment_method' => FulfilmentMethod::Pickup,
        'customer_name' => 'Provider Sync',
        'customer_email' => 'sync@example.test',
        'customer_phone' => '+4790000000',
        'terms_accepted_at' => now(),
        'privacy_accepted_at' => now(),
        'pricing_snapshot_at' => now(),
        'submitted_at' => now(),
    ]);
    $order->items()->create([
        'external_sku' => 'SYNC-SKU',
        'product_title' => 'Sync tire',
        'quantity' => 1,
        'unit_price_minor' => 149900,
        'line_total_minor' => 149900,
        'currency' => 'NOK',
        'product_snapshot' => ['type' => 'tire'],
        'pricing_snapshot' => ['source' => 'test'],
    ]);

    return $order->load('items');
}

function providerSyncTransaction(Order $order, ProviderPaymentState $state, string $external, string $updatedAt): PaymentTransactionResult
{
    return new PaymentTransactionResult(
        providerReference: 'txn-1',
        providerSessionReference: 'session-1',
        merchantReference: $order->public_reference,
        state: $state,
        amountMinor: $order->total_minor,
        currency: $order->currency,
        externalStatus: $external,
        paymentProductType: 'card',
        updatedAt: $updatedAt,
    );
}
