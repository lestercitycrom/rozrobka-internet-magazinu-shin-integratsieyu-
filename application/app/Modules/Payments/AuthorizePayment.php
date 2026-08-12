<?php

namespace App\Modules\Payments;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Contracts\OrderPaymentReadiness;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\PaymentAuthorizationRequest;
use App\Modules\Payments\Exceptions\PaymentOperationRejected;
use App\Modules\Payments\Exceptions\PaymentProviderRejected;
use App\Modules\Payments\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;

final class AuthorizePayment
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly OrderPaymentReadiness $readiness,
    ) {}

    public function execute(Order $order, string $idempotencyKey, string $returnUrl): PaymentAttempt
    {
        PaymentBoundaryGuard::assertEnabled();
        $provider = PaymentBoundaryGuard::provider($this->gateway->provider());
        $idempotencyKey = PaymentBoundaryGuard::idempotencyKey($idempotencyKey);
        if (filter_var($returnUrl, FILTER_VALIDATE_URL) === false || ! in_array(parse_url($returnUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new PaymentOperationRejected('A valid payment return URL is required.');
        }
        if (! PaymentAttempt::query()->where('order_id', $order->getKey())->exists()) {
            $this->readiness->assertReady($order);
        }

        $attempt = DB::transaction(function () use ($order, $provider, $idempotencyKey): PaymentAttempt {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            PaymentBoundaryGuard::money($locked->total_minor, $locked->currency);

            /** @var PaymentAttempt|null $existing */
            $existing = PaymentAttempt::query()->where('order_id', $locked->id)->lockForUpdate()->first();
            if ($existing !== null) {
                $this->assertSameAuthorization($existing, $locked, $provider, $idempotencyKey);
                if ($existing->status === PaymentStatus::Failed) {
                    throw new PaymentOperationRejected('The authorization was definitively rejected.');
                }

                return $existing;
            }

            if ($locked->status !== OrderStatus::AwaitingPayment || $locked->payment_status !== PaymentStatus::NotStarted) {
                throw new PaymentOperationRejected('The order is not ready for payment authorization.');
            }

            $attempt = PaymentAttempt::create([
                'order_id' => $locked->id,
                'provider' => $provider,
                'idempotency_key' => $idempotencyKey,
                'status' => PaymentStatus::Pending,
                'amount_minor' => $locked->total_minor,
                'currency' => $locked->currency,
                'started_at' => now(),
            ]);
            $locked->update(['payment_status' => PaymentStatus::Pending]);

            return $attempt;
        }, 3);

        if ($attempt->status !== PaymentStatus::Pending) {
            return $attempt;
        }

        /** @var Order $paymentOrder */
        $paymentOrder = Order::query()->findOrFail($attempt->order_id);

        try {
            $result = $this->gateway->authorize(new PaymentAuthorizationRequest(
                orderId: (string) $paymentOrder->getKey(),
                orderReference: $paymentOrder->public_reference,
                amountMinor: $attempt->amount_minor,
                currency: $attempt->currency,
                idempotencyKey: $attempt->idempotency_key,
                returnUrl: $returnUrl,
            ));
        } catch (PaymentProviderRejected $exception) {
            $this->markRejected($attempt);

            throw $exception;
        }

        $reference = PaymentBoundaryGuard::providerReference($result->providerReference);

        return DB::transaction(function () use ($attempt, $reference, $result): PaymentAttempt {
            /** @var PaymentAttempt $lockedAttempt */
            $lockedAttempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($lockedAttempt->order_id);

            if ($lockedAttempt->status === PaymentStatus::Authorized) {
                if (! hash_equals((string) $lockedAttempt->provider_reference, $reference)) {
                    throw new PaymentOperationRejected('Authorization retry returned a different provider reference.');
                }

                return $lockedAttempt;
            }
            if ($lockedAttempt->status !== PaymentStatus::Pending || $lockedOrder->payment_status !== PaymentStatus::Pending) {
                throw new PaymentOperationRejected('The authorization state changed before confirmation.');
            }

            $lockedAttempt->update([
                'provider_reference' => $reference,
                'status' => PaymentStatus::Authorized,
                'metadata_encrypted' => $result->redirectUrl === null ? null : ['redirect_url' => $result->redirectUrl],
                'authorized_at' => now(),
            ]);
            $lockedOrder->update(['payment_status' => PaymentStatus::Authorized]);

            return $lockedAttempt->refresh();
        }, 3);
    }

    private function assertSameAuthorization(PaymentAttempt $attempt, Order $order, string $provider, string $key): void
    {
        if (! hash_equals($attempt->idempotency_key, $key)
            || ! hash_equals($attempt->provider, $provider)
            || $attempt->amount_minor !== $order->total_minor
            || ! hash_equals($attempt->currency, $order->currency)) {
            throw new PaymentOperationRejected('A payment lifecycle already exists for this order.');
        }
    }

    private function markRejected(PaymentAttempt $attempt): void
    {
        DB::transaction(function () use ($attempt): void {
            /** @var PaymentAttempt $lockedAttempt */
            $lockedAttempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($lockedAttempt->status !== PaymentStatus::Pending) {
                return;
            }
            $lockedAttempt->update([
                'status' => PaymentStatus::Failed,
                'metadata_encrypted' => ['last_error_code' => 'provider_rejected'],
                'completed_at' => now(),
            ]);
            Order::query()->whereKey($lockedAttempt->order_id)->update(['payment_status' => PaymentStatus::Failed]);
        }, 3);
    }
}
