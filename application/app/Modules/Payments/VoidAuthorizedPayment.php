<?php

namespace App\Modules\Payments;

use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\QueueCustomerOrderMessage;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\PaymentOperationRequest;
use App\Modules\Payments\Enums\PaymentOperationStatus;
use App\Modules\Payments\Enums\PaymentOperationType;
use App\Modules\Payments\Exceptions\PaymentOperationRejected;
use App\Modules\Payments\Exceptions\PaymentProviderRejected;
use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Payments\Models\PaymentOperation;
use Illuminate\Support\Facades\DB;

final class VoidAuthorizedPayment
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly QueueCustomerOrderMessage $customerMessages,
    ) {}

    public function execute(Order $order, string $idempotencyKey, ?int $actorUserId = null, ?string $note = null): PaymentAttempt
    {
        PaymentBoundaryGuard::assertEnabled();
        PaymentBoundaryGuard::assertOrderAllowed($order);
        $provider = PaymentBoundaryGuard::provider($this->gateway->provider());
        $idempotencyKey = PaymentBoundaryGuard::idempotencyKey($idempotencyKey);

        [$attempt, $operation] = DB::transaction(function () use ($order, $provider, $idempotencyKey, $actorUserId, $note): array {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            /** @var PaymentAttempt|null $attempt */
            $attempt = PaymentAttempt::query()->where('order_id', $lockedOrder->id)->lockForUpdate()->first();
            if ($attempt === null || ! hash_equals($attempt->provider, $provider)) {
                throw new PaymentOperationRejected('No matching authorization exists for this order.');
            }
            if ($attempt->status === PaymentStatus::Voided) {
                $this->assertOperationKey($attempt->void_idempotency_key, $idempotencyKey);

                return [$attempt, PaymentOperation::query()->where('idempotency_key', $idempotencyKey)->first()];
            }
            if ($lockedOrder->payment_status !== PaymentStatus::Authorized
                || ! in_array($attempt->status, [PaymentStatus::Authorized, PaymentStatus::Voiding], true)) {
                throw new PaymentOperationRejected('Only an uncaptured authorization can be voided.');
            }
            if ($attempt->void_idempotency_key !== null) {
                $this->assertOperationKey($attempt->void_idempotency_key, $idempotencyKey);
            } else {
                $attempt->update(['void_idempotency_key' => $idempotencyKey, 'status' => PaymentStatus::Voiding]);
            }
            $operation = PaymentOperation::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'order_id' => $lockedOrder->id,
                    'payment_attempt_id' => $attempt->id,
                    'actor_user_id' => $actorUserId,
                    'type' => PaymentOperationType::Void,
                    'status' => PaymentOperationStatus::Pending,
                    'amount_minor' => $attempt->remainingAuthorizedMinor(),
                    'currency' => $attempt->currency,
                    'note' => $note,
                    'started_at' => now(),
                ],
            );

            return [$attempt->refresh(), $operation];
        }, 3);

        if ($attempt->status === PaymentStatus::Voided) {
            return $attempt;
        }

        try {
            $result = $this->gateway->void(new PaymentOperationRequest(
                providerReference: PaymentBoundaryGuard::providerReference((string) $attempt->provider_reference),
                amountMinor: $attempt->remainingAuthorizedMinor(),
                currency: $attempt->currency,
                idempotencyKey: $idempotencyKey,
                operationReference: $order->public_reference,
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
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($lockedAttempt->order_id);
            if ($lockedAttempt->status === PaymentStatus::Voided) {
                return $lockedAttempt;
            }
            if ($lockedAttempt->status !== PaymentStatus::Voiding
                || $lockedOrder->payment_status !== PaymentStatus::Authorized
                || ! hash_equals((string) $lockedAttempt->provider_reference, $reference)) {
                throw new PaymentOperationRejected('Void confirmation does not match the locked payment lifecycle.');
            }

            $lockedAttempt->update(['status' => PaymentStatus::Voided, 'voided_at' => now(), 'completed_at' => now()]);
            $lockedOrder->update(['payment_status' => PaymentStatus::Voided]);
            $operation->update([
                'status' => PaymentOperationStatus::Completed,
                'provider_reference' => $reference,
                'completed_at' => now(),
            ]);
            $lockedOrder->statusHistory()->create([
                'actor_user_id' => $actorUserId,
                'from_status' => $lockedOrder->status,
                'to_status' => $lockedOrder->status,
                'note' => $note,
                'metadata' => ['source' => 'payment_void', 'provider' => $lockedAttempt->provider],
                'occurred_at' => now(),
            ]);

            return $lockedAttempt->refresh();
        }, 3);
        $this->customerMessages->execute($completed->order, 'payment_voided');

        return $completed;
    }

    private function assertOperationKey(?string $stored, string $given): void
    {
        if ($stored === null || ! hash_equals($stored, $given)) {
            throw new PaymentOperationRejected('A different void operation already exists.');
        }
    }

    private function resetDefinitiveRejection(PaymentAttempt $attempt, PaymentOperation $operation): void
    {
        DB::transaction(function () use ($attempt, $operation): void {
            /** @var PaymentAttempt|null $lockedAttempt */
            $lockedAttempt = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->first();
            if ($lockedAttempt === null || $lockedAttempt->status !== PaymentStatus::Voiding) {
                return;
            }
            $lockedAttempt->update([
                'status' => PaymentStatus::Authorized,
                'void_idempotency_key' => null,
                'metadata_encrypted' => ['last_error_code' => 'void_rejected'],
            ]);
            $operation->update([
                'status' => PaymentOperationStatus::Rejected,
                'error_code' => 'void_rejected',
                'completed_at' => now(),
            ]);
        }, 3);
    }
}
