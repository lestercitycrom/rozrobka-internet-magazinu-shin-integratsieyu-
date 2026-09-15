<?php

namespace App\Modules\Payments;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Contracts\OrderPaymentReadiness;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\PaymentAuthorizationRequest;
use App\Modules\Payments\Dintero\DinteroOrderPayload;
use App\Modules\Payments\Enums\ProviderPaymentState;
use App\Modules\Payments\Exceptions\PaymentOperationRejected;
use App\Modules\Payments\Exceptions\PaymentProviderRejected;
use App\Modules\Payments\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;
use Throwable;

final class AuthorizePayment
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly OrderPaymentReadiness $readiness,
        private readonly DinteroOrderPayload $payload,
    ) {}

    public function execute(
        Order $order,
        string $idempotencyKey,
        string $returnUrl,
        ?string $callbackUrl = null,
        ?string $termsUrl = null,
    ): PaymentAttempt {
        PaymentBoundaryGuard::assertEnabled();
        PaymentBoundaryGuard::assertOrderAllowed($order);
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
        if ($attempt->provider_session_reference !== null) {
            return $attempt;
        }
        $attemptMetadata = is_array($attempt->metadata_encrypted) ? $attempt->metadata_encrypted : [];
        if (($attemptMetadata['authorization_ambiguous'] ?? false) === true) {
            throw new PaymentOperationRejected('The previous authorization request is ambiguous and requires reconciliation.');
        }

        /** @var Order $paymentOrder */
        $paymentOrder = Order::query()->with(['items', 'serviceItems'])->findOrFail($attempt->order_id);

        try {
            $result = $this->gateway->authorize(new PaymentAuthorizationRequest(
                orderId: (string) $paymentOrder->getKey(),
                orderReference: $paymentOrder->public_reference,
                amountMinor: $attempt->amount_minor,
                currency: $attempt->currency,
                idempotencyKey: $attempt->idempotency_key,
                returnUrl: $returnUrl,
                callbackUrl: $callbackUrl ?? $returnUrl,
                termsUrl: $termsUrl,
                customerName: $paymentOrder->customer_name,
                customerEmail: $paymentOrder->customer_email,
                customerPhone: $paymentOrder->customer_phone,
                items: $this->payload->authorizationItems($paymentOrder),
            ));
        } catch (PaymentProviderRejected $exception) {
            $this->markRejected($attempt);

            throw $exception;
        } catch (Throwable $exception) {
            $this->markAmbiguous($attempt);

            throw $exception;
        }

        $sessionReference = PaymentBoundaryGuard::providerReference($result->providerSessionReference ?? $result->providerReference);
        $providerReference = $result->state === ProviderPaymentState::Authorized
            ? PaymentBoundaryGuard::providerReference($result->providerReference)
            : null;

        return DB::transaction(function () use ($attempt, $sessionReference, $providerReference, $result): PaymentAttempt {
            /** @var PaymentAttempt $lockedAttempt */
            $lockedAttempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($lockedAttempt->order_id);

            if ($lockedAttempt->status === PaymentStatus::Authorized) {
                if ($providerReference === null || ! hash_equals((string) $lockedAttempt->provider_reference, $providerReference)) {
                    throw new PaymentOperationRejected('Authorization retry returned a different provider reference.');
                }

                return $lockedAttempt;
            }
            if ($lockedAttempt->status !== PaymentStatus::Pending || $lockedOrder->payment_status !== PaymentStatus::Pending) {
                throw new PaymentOperationRejected('The authorization state changed before confirmation.');
            }

            $lockedAttempt->update([
                'provider_reference' => $providerReference,
                'provider_session_reference' => $sessionReference,
                'status' => $result->state === ProviderPaymentState::Authorized ? PaymentStatus::Authorized : PaymentStatus::Pending,
                'metadata_encrypted' => array_filter([
                    'redirect_url' => $result->redirectUrl,
                    'provider_state' => $result->state->value,
                ], fn ($value): bool => $value !== null),
                'authorized_at' => $result->state === ProviderPaymentState::Authorized ? now() : null,
            ]);
            $lockedOrder->update([
                'payment_status' => $result->state === ProviderPaymentState::Authorized ? PaymentStatus::Authorized : PaymentStatus::Pending,
            ]);

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

    private function markAmbiguous(PaymentAttempt $attempt): void
    {
        DB::transaction(function () use ($attempt): void {
            /** @var PaymentAttempt|null $lockedAttempt */
            $lockedAttempt = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->first();
            if ($lockedAttempt === null || $lockedAttempt->status !== PaymentStatus::Pending) {
                return;
            }
            $lockedAttempt->update([
                'metadata_encrypted' => [
                    'authorization_ambiguous' => true,
                    'last_error_code' => 'authorization_transport_or_protocol_unknown',
                ],
            ]);
        }, 3);
    }
}
