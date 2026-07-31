<?php

use App\Modules\Cart\Data\CartLine;
use App\Modules\Cart\Data\CartSnapshot;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\ReadModel\CatalogItem;
use App\Modules\Catalog\ReadModel\CatalogStock;
use App\Modules\Orders\CreatePendingOrder;
use App\Modules\Orders\Data\CheckoutData;
use App\Modules\Orders\Enums\FulfilmentMethod;
use App\Modules\Orders\Enums\NdiOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\TransitionOrder;
use App\Modules\Outbox\Models\OutboxMessage;
use App\Modules\Payments\ConfirmPayment;
use App\Modules\Payments\Exceptions\PaymentConfirmationRejected;
use App\Modules\Payments\Models\PaymentWebhookEvent;
use App\Modules\Pricing\Data\SellingPrice;
use App\Modules\Pricing\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('checkout freezes an immutable snapshot and duplicate idempotency key returns the same order', function (): void {
    $data = commerceCheckoutData();
    $cart = commerceCart();
    $creator = app(CreatePendingOrder::class);

    $first = $creator->execute($data, $cart);
    $second = $creator->execute($data, $cart);

    expect($first->created)->toBeTrue()
        ->and($second->created)->toBeFalse()
        ->and($second->order->id)->toBe($first->order->id)
        ->and(Order::query()->count())->toBe(1)
        ->and($first->order->items()->first()->unit_price_minor)->toBe(125000)
        ->and($first->order->items()->first()->line_total_minor)->toBe(500000)
        ->and($first->order->status)->toBe(OrderStatus::PendingReview)
        ->and($first->order->payment_status)->toBe(PaymentStatus::NotStarted)
        ->and($first->order->ndi_status)->toBe(NdiOrderStatus::NotSubmitted)
        ->and($first->order->statusHistory()->count())->toBe(1);
});

test('order transitions are explicit and fulfilment requires confirmed payment', function (): void {
    $order = app(CreatePendingOrder::class)->execute(commerceCheckoutData(), commerceCart())->order;
    $transition = app(TransitionOrder::class);
    $transition->execute($order, OrderStatus::AwaitingPayment, note: 'Reviewed locally');

    expect(fn () => $transition->execute($order->refresh(), OrderStatus::FulfilmentPending))
        ->toThrow(InvalidOrderTransition::class)
        ->and($order->statusHistory()->count())->toBe(2);
});

test('confirmed payment is idempotent and publishes encrypted outbox messages after commit', function (): void {
    $order = app(CreatePendingOrder::class)->execute(commerceCheckoutData(), commerceCart())->order;
    app(TransitionOrder::class)->execute($order, OrderStatus::AwaitingPayment, note: 'Approved');
    $order->update([
        'ndi_status' => NdiOrderStatus::Accepted,
        'payment_status' => PaymentStatus::Authorized,
    ]);
    $confirm = app(ConfirmPayment::class);

    $paid = $confirm->execute($order, 'fake', 'event-1', 'provider-ref-1', 500000, 'NOK', hash('sha256', 'fixture'));
    $same = $confirm->execute($paid, 'fake', 'event-1', 'provider-ref-1', 500000, 'NOK', hash('sha256', 'fixture'));

    expect($paid->status)->toBe(OrderStatus::Paid)
        ->and($paid->payment_status)->toBe(PaymentStatus::Paid)
        ->and($same->id)->toBe($paid->id)
        ->and(PaymentWebhookEvent::query()->count())->toBe(1)
        ->and(OutboxMessage::query()->count())->toBe(2)
        ->and(OutboxMessage::query()->pluck('type')->all())->toContain('notion.order_paid', 'telegram.order_paid')
        ->and(OutboxMessage::query()->first()->getRawOriginal('payload_encrypted'))->not->toContain('buyer@example.test');
});

test('payment confirmation rejects price drift and an order not approved for payment', function (): void {
    $order = app(CreatePendingOrder::class)->execute(commerceCheckoutData(), commerceCart())->order;

    expect(fn () => app(ConfirmPayment::class)->execute($order, 'fake', 'event-2', 'provider-ref-2', 499999, 'NOK', hash('sha256', 'fixture-2')))
        ->toThrow(PaymentConfirmationRejected::class)
        ->and(PaymentWebhookEvent::query()->count())->toBe(0)
        ->and(OutboxMessage::query()->count())->toBe(0);
});

test('payment capture is rejected until NDI has accepted the order', function (): void {
    $order = app(CreatePendingOrder::class)->execute(commerceCheckoutData(), commerceCart())->order;
    app(TransitionOrder::class)->execute($order, OrderStatus::AwaitingPayment, note: 'Approved');

    expect(fn () => app(ConfirmPayment::class)->execute($order, 'fake', 'event-3', 'provider-ref-3', 500000, 'NOK', hash('sha256', 'fixture-3')))
        ->toThrow(PaymentConfirmationRejected::class, 'NDI')
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::NotStarted)
        ->and(PaymentWebhookEvent::query()->count())->toBe(0);
});

function commerceCheckoutData(): CheckoutData
{
    return new CheckoutData(
        idempotencyKey: (string) Str::uuid(),
        locale: 'nb',
        customerName: 'Test Buyer',
        customerEmail: 'buyer@example.test',
        customerPhone: '+47 900 00 000',
        fulfilmentMethod: FulfilmentMethod::Pickup,
        vehicleRegistration: 'AB12345',
        customerNote: null,
    );
}

function commerceCart(): CartSnapshot
{
    $money = new Money(125000, 'NOK');
    $item = new CatalogItem(
        sku: 'ORDER-SKU',
        productType: ProductType::Tire,
        brand: 'Nordlys',
        modelName: 'Arctic',
        title: 'Nordlys Arctic',
        prices: [],
        stock: new CatalogStock(8, now()->toISOString()),
        tire: ['width_mm' => '205.00', 'aspect_ratio' => '55.00', 'rim_diameter_in' => '16.00'],
        wheel: null,
    );
    $sellingPrice = new SellingPrice($money, new Money(100000, 'NOK'), 1, 'fixture', 'public', now()->toISOString());

    return new CartSnapshot([new CartLine($item, $sellingPrice, 4)], [], $money->multiply(4));
}
