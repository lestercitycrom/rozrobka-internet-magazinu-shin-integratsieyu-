<?php

use App\Models\User;
use App\Modules\Orders\Enums\FulfilmentMethod;
use App\Modules\Orders\Enums\NdiOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Outbox\Models\OutboxMessage;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Models\PaymentAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\FakePaymentGateway;

uses(RefreshDatabase::class);

test('operations admin explicitly confirms goods ready before Dintero capture', function (): void {
    config()->set(['payments.enabled' => true, 'payments.provider' => 'fake']);
    $gateway = new FakePaymentGateway;
    app()->instance(PaymentGateway::class, $gateway);
    $admin = User::factory()->owner()->create();
    $order = adminPayableOrder();
    PaymentAttempt::create([
        'order_id' => $order->id,
        'provider' => 'fake',
        'idempotency_key' => (string) Str::uuid(),
        'provider_reference' => 'txn-admin-1',
        'provider_session_reference' => 'session-admin-1',
        'status' => PaymentStatus::Authorized,
        'amount_minor' => $order->total_minor,
        'currency' => $order->currency,
        'started_at' => now(),
        'authorized_at' => now(),
    ]);

    $this->actingAs($admin)->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee('data-testid="admin-order-payment"', false)
        ->assertSee('data-testid="admin-capture-payment"', false)
        ->assertSee(__('order_ui.capture_help'));

    $this->actingAs($admin)->post(route('admin.orders.payment.capture', $order), [
        'note' => 'Varene er kontrollert og klare for montering.',
    ])->assertRedirect();

    expect($order->refresh()->goods_ready_at)->not->toBeNull()
        ->and($order->goods_ready_by_user_id)->toBe($admin->id)
        ->and($order->goods_ready_note)->toBe('Varene er kontrollert og klare for montering.')
        ->and($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->status)->toBe(OrderStatus::Paid)
        ->and($gateway->captureCalls)->toHaveCount(1)
        ->and(OutboxMessage::query()->count())->toBe(2);
});

test('capture endpoint rejects an order not accepted by NDI without changing readiness', function (): void {
    config()->set(['payments.enabled' => true, 'payments.provider' => 'fake']);
    $gateway = new FakePaymentGateway;
    app()->instance(PaymentGateway::class, $gateway);
    $admin = User::factory()->owner()->create();
    $order = adminPayableOrder();
    $order->update(['ndi_status' => NdiOrderStatus::NotSubmitted]);
    PaymentAttempt::create([
        'order_id' => $order->id,
        'provider' => 'fake',
        'idempotency_key' => (string) Str::uuid(),
        'provider_reference' => 'txn-admin-2',
        'provider_session_reference' => 'session-admin-2',
        'status' => PaymentStatus::Authorized,
        'amount_minor' => $order->total_minor,
        'currency' => $order->currency,
        'started_at' => now(),
        'authorized_at' => now(),
    ]);

    $this->actingAs($admin)->post(route('admin.orders.payment.capture', $order), [
        'note' => 'Must not pass',
    ])->assertStatus(409);

    expect($order->refresh()->goods_ready_at)->toBeNull()
        ->and($order->payment_status)->toBe(PaymentStatus::Authorized)
        ->and($gateway->captureCalls)->toBeEmpty();
});

function adminPayableOrder(): Order
{
    $order = Order::create([
        'public_reference' => 'ET-'.Str::upper(Str::random(14)),
        'idempotency_key' => (string) Str::uuid(),
        'locale' => 'nb',
        'status' => OrderStatus::AwaitingPayment,
        'payment_status' => PaymentStatus::Authorized,
        'ndi_status' => NdiOrderStatus::Accepted,
        'currency' => 'NOK',
        'subtotal_minor' => 149900,
        'total_minor' => 149900,
        'fulfilment_method' => FulfilmentMethod::Pickup,
        'customer_name' => 'Admin payment test',
        'customer_email' => 'admin-payment@example.test',
        'customer_phone' => '+4790000000',
        'terms_accepted_at' => now(),
        'privacy_accepted_at' => now(),
        'pricing_snapshot_at' => now(),
        'submitted_at' => now(),
    ]);
    $order->items()->create([
        'external_sku' => 'ADMIN-PAY-SKU',
        'product_title' => 'Admin payment tire',
        'quantity' => 1,
        'unit_price_minor' => 149900,
        'line_total_minor' => 149900,
        'currency' => 'NOK',
        'product_snapshot' => ['type' => 'tire'],
        'pricing_snapshot' => ['source' => 'test'],
    ]);

    return $order;
}
