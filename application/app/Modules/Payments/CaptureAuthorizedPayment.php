<?php

namespace App\Modules\Payments;

use App\Modules\Orders\Enums\NdiOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Outbox\OutboxPublisher;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\PaymentOperationRequest;
use App\Modules\Payments\Dintero\DinteroOrderPayload;
use App\Modules\Payments\Exceptions\PaymentOperationRejected;
use App\Modules\Payments\Exceptions\PaymentProviderRejected;
use App\Modules\Payments\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;

final class CaptureAuthorizedPayment
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly OutboxPublisher $outbox,
        private readonly PaidOrderPayload $paidOrderPayload,
        private readonly DinteroOrderPayload $payload,
    ) {}

    public function execute(Order $order, string $idempotencyKey): PaymentAttempt
    {
        PaymentBoundaryGuard::assertEnabled();
        $provider = PaymentBoundaryGuard::provider($this->gateway->provider());
        $idempotencyKey = PaymentBoundaryGuard::idempotencyKey($idempotencyKey);

        $attempt = DB::transaction(function () use ($order, $provider, $idempotencyKey): PaymentAttempt {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            /** @var PaymentAttempt|null $attempt */
            $attempt = PaymentAttempt::query()->where('order_id', $lockedOrder->id)->lockForUpdate()->first();
            if ($attempt === null || ! hash_equals($attempt->provider, $provider)) {
                throw new PaymentOperationRejected('No matching authorization exists for this order.');
            }
            if ($attempt->status === PaymentStatus::Paid) {
                $this->assertOperationKey($attempt->capture_idempotency_key, $idempotencyKey, 'capture');

                return $attempt;
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
            if ($attempt->capture_idempotency_key !== null) {
                $this->assertOperationKey($attempt->capture_idempotency_key, $idempotencyKey, 'capture');
            } else {
                $attempt->update(['capture_idempotency_key' => $idempotencyKey, 'status' => PaymentStatus::Capturing]);
            }

            return $attempt->refresh();
        }, 3);

        if ($attempt->status === PaymentStatus::Paid) {
            return $attempt;
        }

        $paymentOrder = Order::query()->with(['items', 'serviceItems'])->findOrFail($attempt->order_id);
        try {
            $result = $this->gateway->capture(new PaymentOperationRequest(
                providerReference: PaymentBoundaryGuard::providerReference((string) $attempt->provider_reference),
                amountMinor: $attempt->amount_minor,
                currency: $attempt->currency,
                idempotencyKey: $attempt->capture_idempotency_key,
                operationReference: $paymentOrder->public_reference,
                items: $this->payload->operationItems($paymentOrder),
            ));
        } catch (PaymentProviderRejected $exception) {
            $this->resetDefinitiveRejection($attempt);

            throw $exception;
        }

        $reference = PaymentBoundaryGuard::providerReference($result->providerReference);

        return DB::transaction(function () use ($attempt, $reference): PaymentAttempt {
            /** @var PaymentAttempt $lockedAttempt */
            $lockedAttempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->with(['items', 'serviceItems'])->lockForUpdate()->findOrFail($lockedAttempt->order_id);
            if ($lockedAttempt->status === PaymentStatus::Paid) {
                return $lockedAttempt;
            }
            if ($lockedAttempt->status !== PaymentStatus::Capturing
                || $lockedOrder->payment_status !== PaymentStatus::Authorized
                || $lockedOrder->ndi_status !== NdiOrderStatus::Accepted
                || $lockedOrder->goods_ready_at === null
                || ! hash_equals((string) $lockedAttempt->provider_reference, $reference)) {
                throw new PaymentOperationRejected('Capture confirmation does not match the locked payment lifecycle.');
            }

            $lockedAttempt->update(['status' => PaymentStatus::Paid, 'captured_at' => now(), 'completed_at' => now()]);
            $from = $lockedOrder->status;
            $lockedOrder->update(['payment_status' => PaymentStatus::Paid, 'status' => OrderStatus::Paid]);
            $lockedOrder->statusHistory()->create([
                'from_status' => $from,
                'to_status' => OrderStatus::Paid,
                'metadata' => ['source' => 'payment_capture', 'provider' => $lockedAttempt->provider],
                'occurred_at' => now(),
            ]);
            $payload = $this->paidOrderPayload->for($lockedOrder);
            $this->outbox->forOrder($lockedOrder, 'notion.order_paid', "notion:order-paid:{$lockedOrder->id}", $payload);
            $this->outbox->forOrder($lockedOrder, 'telegram.order_paid', "telegram:order-paid:{$lockedOrder->id}", $payload);

            return $lockedAttempt->refresh();
        }, 3);
    }

    private function assertOperationKey(?string $stored, string $given, string $operation): void
    {
        if ($stored === null || ! hash_equals($stored, $given)) {
            throw new PaymentOperationRejected("A different {$operation} operation already exists.");
        }
    }

    private function resetDefinitiveRejection(PaymentAttempt $attempt): void
    {
        DB::transaction(function () use ($attempt): void {
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
        }, 3);
    }
}
