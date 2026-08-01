<?php

namespace App\Modules\Payments;

use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\PaymentOperationRequest;
use App\Modules\Payments\Exceptions\PaymentOperationRejected;
use App\Modules\Payments\Exceptions\PaymentProviderRejected;
use App\Modules\Payments\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;

final class VoidAuthorizedPayment
{
    public function __construct(private readonly PaymentGateway $gateway) {}

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
            if ($attempt->status === PaymentStatus::Voided) {
                $this->assertOperationKey($attempt->void_idempotency_key, $idempotencyKey);

                return $attempt;
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

            return $attempt->refresh();
        }, 3);

        if ($attempt->status === PaymentStatus::Voided) {
            return $attempt;
        }

        try {
            $result = $this->gateway->void(new PaymentOperationRequest(
                providerReference: PaymentBoundaryGuard::providerReference((string) $attempt->provider_reference),
                amountMinor: $attempt->amount_minor,
                currency: $attempt->currency,
                idempotencyKey: $attempt->void_idempotency_key,
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

            return $lockedAttempt->refresh();
        }, 3);
    }

    private function assertOperationKey(?string $stored, string $given): void
    {
        if ($stored === null || ! hash_equals($stored, $given)) {
            throw new PaymentOperationRejected('A different void operation already exists.');
        }
    }

    private function resetDefinitiveRejection(PaymentAttempt $attempt): void
    {
        PaymentAttempt::query()->whereKey($attempt->id)->where('status', PaymentStatus::Voiding->value)->update([
            'status' => PaymentStatus::Authorized,
            'void_idempotency_key' => null,
            'metadata_encrypted' => ['last_error_code' => 'void_rejected'],
        ]);
    }
}
