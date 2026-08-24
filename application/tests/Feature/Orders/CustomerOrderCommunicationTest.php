<?php

use App\Mail\OrderCustomerMail;
use App\Modules\Orders\Enums\FulfilmentMethod;
use App\Modules\Orders\Enums\NdiOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\CustomerCommunication;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\QueueCustomerOrderMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('customer order messages are prepared idempotently but staging delivery remains disabled', function (): void {
    Mail::fake();
    config()->set('customer_communications.delivery_enabled', false);
    $order = communicationOrder();

    app(QueueCustomerOrderMessage::class)->execute($order, 'order_accepted');
    app(QueueCustomerOrderMessage::class)->execute($order, 'order_accepted');

    expect(CustomerCommunication::query()->count())->toBe(1)
        ->and(CustomerCommunication::query()->sole()->dispatched_at)->toBeNull();
    Mail::assertNothingQueued();
});

test('verified production delivery flag queues one localized durable confirmation', function (): void {
    Mail::fake();
    config()->set('customer_communications.delivery_enabled', true);
    $order = communicationOrder();

    app(QueueCustomerOrderMessage::class)->execute($order, 'order_accepted');
    app(QueueCustomerOrderMessage::class)->execute($order, 'order_accepted');

    Mail::assertQueued(OrderCustomerMail::class, 1);
    expect(CustomerCommunication::query()->sole()->dispatched_at)->not->toBeNull();
});

function communicationOrder(): Order
{
    $order = Order::create([
        'public_reference' => 'ET-'.Str::upper(Str::random(14)),
        'idempotency_key' => (string) Str::uuid(),
        'locale' => 'nb',
        'status' => OrderStatus::AwaitingPayment,
        'payment_status' => PaymentStatus::Authorized,
        'ndi_status' => NdiOrderStatus::Accepted,
        'currency' => 'NOK',
        'subtotal_minor' => 10000,
        'total_minor' => 10000,
        'fulfilment_method' => FulfilmentMethod::Pickup,
        'customer_name' => 'Communication test',
        'customer_email' => 'communication@example.test',
        'customer_phone' => '+4790000000',
        'terms_accepted_at' => now(),
        'privacy_accepted_at' => now(),
        'pricing_snapshot_at' => now(),
        'submitted_at' => now(),
    ]);
    $order->items()->create([
        'external_sku' => 'MAIL-SKU',
        'product_title' => 'Mail test tire',
        'quantity' => 1,
        'unit_price_minor' => 10000,
        'line_total_minor' => 10000,
        'currency' => 'NOK',
        'product_snapshot' => ['type' => 'tire'],
        'pricing_snapshot' => ['source' => 'test'],
    ]);

    return $order;
}
