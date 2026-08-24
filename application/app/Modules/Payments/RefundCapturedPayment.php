<?php

namespace App\Modules\Payments;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\QueueCustomerOrderMessage;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\PaymentOperationRequest;
use App\Modules\Payments\Dintero\DinteroOrderPayload;
use App\Modules\Payments\Enums\PaymentOperationStatus;
use App\Modules\Payments\Enums\PaymentOperationType;
use App\Modules\Payments\Exceptions\PaymentOperationRejected;
use App\Modules\Payments\Exceptions\PaymentProviderRejected;
use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Payments\Models\PaymentOperation;
use Illuminate\Support\Facades\DB;

final class RefundCapturedPayment
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly DinteroOrderPayload $payload,
        private readonly QueueCustomerOrderMessage $customerMessages,
    ) {}

    /** @param array<string, int|string> $quantities keyed by immutable Dintero line_id */
    public function execute(
        Order $order,
        string $idempotencyKey,
        array $quantities,
        ?int $actorUserId = null,
        ?string $note = null,
    ): PaymentAttempt {
        PaymentBoundaryGuard::assertEnabled();
        $provider = PaymentBoundaryGuard::provider($this->gateway->provider());
        $idempotencyKey = PaymentBoundaryGuard::idempotencyKey($idempotencyKey);
        $paymentOrder = Order::query()->with(['items', 'serviceItems'])->findOrFail($order->getKey());
        $items = $this->payload->operationItemsForQuantities($paymentOrder, $quantities);
        $amountMinor = $this->payload->operationAmount($items);

        [$attempt, $operation] = DB::transaction(function () use (
            $order,
            $provider,
            $idempotencyKey,
            $items,
            $amountMinor,
            $actorUserId,
            $note,
        ): array {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            /** @var PaymentAttempt|null $attempt */
            $attempt = PaymentAttempt::query()->where('order_id', $lockedOrder->id)->lockForUpdate()->first();
            if ($attempt === null || ! hash_equals($attempt->provider, $provider)) {
                throw new PaymentOperationRejected('No matching captured payment exists for this order.');
            }
            /** @var PaymentOperation|null $existing */
            $existing = PaymentOperation::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->payment_attempt_id !== $attempt->id || $existing->type !== PaymentOperationType::Refund) {
                    throw new PaymentOperationRejected('Refund idempotency key belongs to another operation.');
                }

                return [$attempt, $existing];
            }
            if (! in_array($attempt->status, [PaymentStatus::Paid, PaymentStatus::PartiallyPaid, PaymentStatus::PartiallyRefunded], true)) {
                throw new PaymentOperationRejected('Only a captured payment can be refunded.');
            }
            if ($amountMinor > $attempt->refundableMinor()) {
                throw new PaymentOperationRejected('Refund exceeds the remaining captured amount.');
            }
            $this->assertLineQuantitiesRemainRefundable($attempt, $items);
            $attempt->update([
                'status' => PaymentStatus::Refunding,
                'refund_idempotency_key' => $idempotencyKey,
            ]);
            $operation = PaymentOperation::query()->create([
                'order_id' => $lockedOrder->id,
                'payment_attempt_id' => $attempt->id,
                'actor_user_id' => $actorUserId,
                'type' => PaymentOperationType::Refund,
                'idempotency_key' => $idempotencyKey,
                'status' => PaymentOperationStatus::Pending,
                'amount_minor' => $amountMinor,
                'currency' => $attempt->currency,
                'items_encrypted' => $items,
                'note' => $note,
                'started_at' => now(),
            ]);

            return [$attempt->refresh(), $operation];
        }, 3);

        if ($operation->status === PaymentOperationStatus::Completed) {
            return $attempt;
        }
        if ($operation->status === PaymentOperationStatus::Rejected) {
            throw new PaymentOperationRejected('This refund was rejected and requires a new reviewed operation.');
        }

        try {
            $result = $this->gateway->refund(new PaymentOperationRequest(
                providerReference: PaymentBoundaryGuard::providerReference((string) $attempt->provider_reference),
                amountMinor: $operation->amount_minor,
                currency: $attempt->currency,
                idempotencyKey: $operation->idempotency_key,
                operationReference: $paymentOrder->public_reference,
                items: $operation->items_encrypted ?? [],
                reason: $note,
            ));
        } catch (PaymentProviderRejected $exception) {
            $this->resetDefinitiveRejection($attempt, $operation);

            throw $exception;
        }

        $reference = PaymentBoundaryGuard::providerReference($result->providerReference);

        $completed = DB::transaction(function () use ($attempt, $operation, $reference, $actorUserId, $note): PaymentAttempt {
            /** @var PaymentAttempt $lockedAttempt */
            $lockedAttempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            /** @var PaymentOperation $lockedOperation */
            $lockedOperation = PaymentOperation::query()->lockForUpdate()->findOrFail($operation->id);
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($lockedAttempt->order_id);
            if ($lockedOperation->status === PaymentOperationStatus::Completed) {
                return $lockedAttempt;
            }
            if ($lockedAttempt->status !== PaymentStatus::Refunding
                || $lockedOperation->status !== PaymentOperationStatus::Pending
                || ! hash_equals((string) $lockedAttempt->provider_reference, $reference)) {
                throw new PaymentOperationRejected('Refund confirmation does not match the locked payment lifecycle.');
            }

            $refundedAmount = $lockedAttempt->refunded_amount_minor + $lockedOperation->amount_minor;
            $full = $refundedAmount >= $lockedAttempt->captured_amount_minor;
            $paymentStatus = $full ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded;
            $lockedAttempt->update([
                'status' => $paymentStatus,
                'refunded_amount_minor' => $refundedAmount,
                'refunded_at' => now(),
                'completed_at' => $full ? now() : $lockedAttempt->completed_at,
            ]);
            $lockedOperation->update([
                'status' => PaymentOperationStatus::Completed,
                'provider_reference' => $reference,
                'completed_at' => now(),
            ]);
            $from = $lockedOrder->status;
            $lockedOrder->update([
                'payment_status' => $paymentStatus,
                'status' => $full ? OrderStatus::Refunded : $lockedOrder->status,
            ]);
            $lockedOrder->statusHistory()->create([
                'actor_user_id' => $actorUserId,
                'from_status' => $from,
                'to_status' => $full ? OrderStatus::Refunded : $from,
                'note' => $note,
                'metadata' => [
                    'source' => 'payment_refund',
                    'provider' => $lockedAttempt->provider,
                    'amount_minor' => $lockedOperation->amount_minor,
                    'full' => $full,
                    'items' => $lockedOperation->items_encrypted,
                ],
                'occurred_at' => now(),
            ]);

            return $lockedAttempt->refresh();
        }, 3);
        $this->customerMessages->execute(
            $completed->order,
            $completed->status === PaymentStatus::Refunded ? 'payment_refunded' : 'payment_partially_refunded',
        );

        return $completed;
    }

    /** @param list<array{line_id:string,quantity:int,amount:int}> $items */
    private function assertLineQuantitiesRemainRefundable(PaymentAttempt $attempt, array $items): void
    {
        $alreadyRefunded = [];
        $operations = PaymentOperation::query()
            ->where('payment_attempt_id', $attempt->id)
            ->where('type', PaymentOperationType::Refund)
            ->where('status', PaymentOperationStatus::Completed)
            ->get();
        foreach ($operations as $operation) {
            foreach ($operation->items_encrypted ?? [] as $item) {
                $lineId = (string) ($item['line_id'] ?? '');
                $alreadyRefunded[$lineId] = ($alreadyRefunded[$lineId] ?? 0) + (int) ($item['quantity'] ?? 0);
            }
        }
        $captured = PaymentOperation::query()
            ->where('payment_attempt_id', $attempt->id)
            ->where('type', PaymentOperationType::Capture)
            ->where('status', PaymentOperationStatus::Completed)
            ->latest('started_at')
            ->first()?->items_encrypted ?? [];
        $capturedByLine = [];
        foreach ($captured as $item) {
            $capturedByLine[(string) $item['line_id']] = (int) $item['quantity'];
        }
        foreach ($items as $item) {
            $lineId = $item['line_id'];
            $remaining = ($capturedByLine[$lineId] ?? 0) - ($alreadyRefunded[$lineId] ?? 0);
            if ($item['quantity'] > $remaining) {
                throw new PaymentOperationRejected('Refund line quantity exceeds the captured, unrefunded quantity.');
            }
        }
    }

    private function resetDefinitiveRejection(PaymentAttempt $attempt, PaymentOperation $operation): void
    {
        DB::transaction(function () use ($attempt, $operation): void {
            /** @var PaymentAttempt|null $lockedAttempt */
            $lockedAttempt = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->first();
            if ($lockedAttempt === null || $lockedAttempt->status !== PaymentStatus::Refunding) {
                return;
            }
            $previousStatus = $lockedAttempt->refunded_amount_minor > 0
                ? PaymentStatus::PartiallyRefunded
                : ($lockedAttempt->captured_amount_minor < $lockedAttempt->amount_minor ? PaymentStatus::PartiallyPaid : PaymentStatus::Paid);
            $lockedAttempt->update([
                'status' => $previousStatus,
                'refund_idempotency_key' => null,
                'metadata_encrypted' => ['last_error_code' => 'refund_rejected'],
            ]);
            $operation->update([
                'status' => PaymentOperationStatus::Rejected,
                'error_code' => 'refund_rejected',
                'completed_at' => now(),
            ]);
        }, 3);
    }
}
