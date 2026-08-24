<?php

namespace App\Modules\Payments;

use App\Modules\Orders\Enums\NdiOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\QueueCustomerOrderMessage;
use App\Modules\Outbox\OutboxPublisher;
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

final class CaptureAuthorizedPayment
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly OutboxPublisher $outbox,
        private readonly PaidOrderPayload $paidOrderPayload,
        private readonly DinteroOrderPayload $payload,
        private readonly QueueCustomerOrderMessage $customerMessages,
    ) {}

    /**
     * @param  array<string, int|string>|null  $quantities  keyed by immutable Dintero line_id
     */
    public function execute(
        Order $order,
        string $idempotencyKey,
        ?array $quantities = null,
        ?int $actorUserId = null,
        ?string $note = null,
        bool $customerAgreement = false,
    ): PaymentAttempt {
        PaymentBoundaryGuard::assertEnabled();
        $provider = PaymentBoundaryGuard::provider($this->gateway->provider());
        $idempotencyKey = PaymentBoundaryGuard::idempotencyKey($idempotencyKey);
        $paymentOrder = Order::query()->with(['items', 'serviceItems'])->findOrFail($order->getKey());
        $items = $quantities === null
            ? $this->payload->operationItems($paymentOrder)
            : $this->payload->operationItemsForQuantities($paymentOrder, $quantities);
        $amountMinor = $this->payload->operationAmount($items);
        $partial = $amountMinor < $paymentOrder->total_minor;
        if ($partial && ! $customerAgreement) {
            throw new PaymentOperationRejected('Partial capture requires a documented customer agreement.');
        }

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
                throw new PaymentOperationRejected('No matching authorization exists for this order.');
            }
            if (in_array($attempt->status, [PaymentStatus::Paid, PaymentStatus::PartiallyPaid], true)) {
                $this->assertOperationKey($attempt->capture_idempotency_key, $idempotencyKey, 'capture');
                $operation = PaymentOperation::query()->where('idempotency_key', $idempotencyKey)->first();

                return [$attempt, $operation];
            }
            if ($attempt->authorizationExpired()) {
                throw new PaymentOperationRejected('The authorization has expired and must not be captured.');
            }
            if ($lockedOrder->status !== OrderStatus::AwaitingPayment
                || $lockedOrder->ndi_status !== NdiOrderStatus::Accepted
                || $lockedOrder->payment_status !== PaymentStatus::Authorized
                || $lockedOrder->goods_ready_at === null) {
                throw new PaymentOperationRejected('Capture requires an authorized payment, an accepted NDI order and explicit goods-ready confirmation.');
            }
            if (! in_array($attempt->status, [PaymentStatus::Authorized, PaymentStatus::Capturing], true)) {
                throw new PaymentOperationRejected('The payment is not available for capture.');
            }
            if ($amountMinor > $attempt->remainingAuthorizedMinor()) {
                throw new PaymentOperationRejected('Capture exceeds the remaining authorization.');
            }
            if ($attempt->capture_idempotency_key !== null) {
                $this->assertOperationKey($attempt->capture_idempotency_key, $idempotencyKey, 'capture');
            } else {
                $attempt->update(['capture_idempotency_key' => $idempotencyKey, 'status' => PaymentStatus::Capturing]);
            }
            $operation = PaymentOperation::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'order_id' => $lockedOrder->id,
                    'payment_attempt_id' => $attempt->id,
                    'actor_user_id' => $actorUserId,
                    'type' => PaymentOperationType::Capture,
                    'status' => PaymentOperationStatus::Pending,
                    'amount_minor' => $amountMinor,
                    'currency' => $attempt->currency,
                    'items_encrypted' => $items,
                    'note' => $note,
                    'started_at' => now(),
                ],
            );

            return [$attempt->refresh(), $operation];
        }, 3);

        if (in_array($attempt->status, [PaymentStatus::Paid, PaymentStatus::PartiallyPaid], true)) {
            return $attempt;
        }

        try {
            $result = $this->gateway->capture(new PaymentOperationRequest(
                providerReference: PaymentBoundaryGuard::providerReference((string) $attempt->provider_reference),
                amountMinor: $amountMinor,
                currency: $attempt->currency,
                idempotencyKey: $idempotencyKey,
                operationReference: $paymentOrder->public_reference,
                items: $items,
                finalCapture: true,
            ));
        } catch (PaymentProviderRejected $exception) {
            $this->resetDefinitiveRejection($attempt, $operation);

            throw $exception;
        }

        $reference = PaymentBoundaryGuard::providerReference($result->providerReference);

        $completed = DB::transaction(function () use ($attempt, $operation, $reference, $amountMinor, $items, $actorUserId, $note): PaymentAttempt {
            /** @var PaymentAttempt $lockedAttempt */
            $lockedAttempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->with(['items', 'serviceItems'])->lockForUpdate()->findOrFail($lockedAttempt->order_id);
            if (in_array($lockedAttempt->status, [PaymentStatus::Paid, PaymentStatus::PartiallyPaid], true)) {
                return $lockedAttempt;
            }
            if ($lockedAttempt->status !== PaymentStatus::Capturing
                || $lockedOrder->payment_status !== PaymentStatus::Authorized
                || $lockedOrder->ndi_status !== NdiOrderStatus::Accepted
                || $lockedOrder->goods_ready_at === null
                || ! hash_equals((string) $lockedAttempt->provider_reference, $reference)) {
                throw new PaymentOperationRejected('Capture confirmation does not match the locked payment lifecycle.');
            }

            $partial = $amountMinor < $lockedAttempt->amount_minor;
            $paymentStatus = $partial ? PaymentStatus::PartiallyPaid : PaymentStatus::Paid;
            $lockedAttempt->update([
                'status' => $paymentStatus,
                'captured_amount_minor' => $amountMinor,
                'captured_at' => now(),
                'completed_at' => now(),
            ]);
            $operation->update([
                'status' => PaymentOperationStatus::Completed,
                'provider_reference' => $reference,
                'completed_at' => now(),
            ]);
            $from = $lockedOrder->status;
            $lockedOrder->update(['payment_status' => $paymentStatus, 'status' => OrderStatus::Paid]);
            $lockedOrder->statusHistory()->create([
                'actor_user_id' => $actorUserId,
                'from_status' => $from,
                'to_status' => OrderStatus::Paid,
                'note' => $note,
                'metadata' => [
                    'source' => 'payment_capture',
                    'provider' => $lockedAttempt->provider,
                    'amount_minor' => $amountMinor,
                    'partial' => $partial,
                    'items' => $items,
                ],
                'occurred_at' => now(),
            ]);
            $payload = $this->paidOrderPayload->for($lockedOrder);
            $this->outbox->forOrder($lockedOrder, 'notion.order_paid', "notion:order-paid:{$lockedOrder->id}", $payload);
            $this->outbox->forOrder($lockedOrder, 'telegram.order_paid', "telegram:order-paid:{$lockedOrder->id}", $payload);

            return $lockedAttempt->refresh();
        }, 3);
        $this->customerMessages->execute(
            $completed->order,
            $completed->status === PaymentStatus::PartiallyPaid ? 'payment_partially_captured' : 'payment_captured',
        );

        return $completed;
    }

    private function assertOperationKey(?string $stored, string $given, string $operation): void
    {
        if ($stored === null || ! hash_equals($stored, $given)) {
            throw new PaymentOperationRejected("A different {$operation} operation already exists.");
        }
    }

    private function resetDefinitiveRejection(PaymentAttempt $attempt, PaymentOperation $operation): void
    {
        DB::transaction(function () use ($attempt, $operation): void {
            /** @var PaymentAttempt|null $lockedAttempt */
            $lockedAttempt = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->first();
            if ($lockedAttempt === null || $lockedAttempt->status !== PaymentStatus::Capturing) {
                return;
            }
            $lockedAttempt->update([
                'status' => PaymentStatus::Authorized,
                'capture_idempotency_key' => null,
                'metadata_encrypted' => ['last_error_code' => 'capture_rejected'],
            ]);
            $operation->update([
                'status' => PaymentOperationStatus::Rejected,
                'error_code' => 'capture_rejected',
                'completed_at' => now(),
            ]);
        }, 3);
    }
}
