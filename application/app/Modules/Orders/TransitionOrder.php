<?php

namespace App\Modules\Orders;

use App\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

final class TransitionOrder
{
    /** @return list<OrderStatus> */
    public function allowedFor(Order $order): array
    {
        return match ($order->status) {
            OrderStatus::PendingReview => [OrderStatus::AwaitingPayment, OrderStatus::Cancelled, OrderStatus::Failed],
            OrderStatus::AwaitingPayment => [OrderStatus::Cancelled, OrderStatus::Failed],
            OrderStatus::Paid => [OrderStatus::FulfilmentPending, OrderStatus::Refunded],
            OrderStatus::FulfilmentPending => [OrderStatus::Fulfilled, OrderStatus::Failed],
            OrderStatus::Failed => [OrderStatus::PendingReview, OrderStatus::Cancelled],
            OrderStatus::Fulfilled, OrderStatus::Cancelled, OrderStatus::Refunded => [],
        };
    }

    public function execute(Order $order, OrderStatus $to, ?User $actor = null, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $to, $actor, $note): Order {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            if (! in_array($to, $this->allowedFor($locked), true)) {
                throw new InvalidOrderTransition("Transition from {$locked->status->value} to {$to->value} is not allowed.");
            }

            if ($to === OrderStatus::FulfilmentPending && $locked->payment_status !== PaymentStatus::Paid) {
                throw new InvalidOrderTransition('Fulfilment cannot start before confirmed payment.');
            }

            if (in_array($to, [OrderStatus::Cancelled, OrderStatus::Failed], true) && blank($note)) {
                throw new InvalidOrderTransition('Cancellation and failure transitions require a note.');
            }

            $from = $locked->status;
            $locked->update(['status' => $to]);
            $locked->statusHistory()->create([
                'actor_user_id' => $actor?->id,
                'from_status' => $from,
                'to_status' => $to,
                'note' => filled($note) ? trim((string) $note) : null,
                'metadata' => ['source' => $actor === null ? 'system' : 'admin'],
                'occurred_at' => now(),
            ]);

            return $locked->refresh();
        }, 3);
    }
}
