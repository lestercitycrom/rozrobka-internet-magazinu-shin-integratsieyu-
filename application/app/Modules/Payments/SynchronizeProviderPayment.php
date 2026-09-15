<?php

namespace App\Modules\Payments;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Outbox\OutboxPublisher;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\PaymentTransactionResult;
use App\Modules\Payments\Enums\ProviderPaymentState;
use App\Modules\Payments\Exceptions\PaymentConfirmationRejected;
use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Payments\Models\PaymentWebhookEvent;
use Illuminate\Support\Facades\DB;

final class SynchronizeProviderPayment
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly OutboxPublisher $outbox,
        private readonly PaidOrderPayload $paidOrderPayload,
        private readonly AuthorizationExpiryPolicy $expiryPolicy,
    ) {}

    public function execute(Order $order, string $providerReference, string $source): PaymentAttempt
    {
        PaymentBoundaryGuard::assertEnabled();
        PaymentBoundaryGuard::assertOrderAllowed($order);
        $provider = PaymentBoundaryGuard::provider($this->gateway->provider());
        $transaction = $this->gateway->transaction(PaymentBoundaryGuard::providerReference($providerReference));
        $this->assertTransaction($order, $transaction);

        $eventId = hash('sha256', implode('|', [
            $transaction->providerReference,
            $transaction->externalStatus,
            $transaction->updatedAt ?? '',
        ]));
        $payloadHash = hash('sha256', json_encode([
            'provider_reference' => $transaction->providerReference,
            'session_reference' => $transaction->providerSessionReference,
            'merchant_reference' => $transaction->merchantReference,
            'status' => $transaction->externalStatus,
            'amount_minor' => $transaction->amountMinor,
            'currency' => $transaction->currency,
            'payment_product_type' => $transaction->paymentProductType,
            'authorization_expires_at' => $transaction->authorizationExpiresAt,
            'updated_at' => $transaction->updatedAt,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($order, $provider, $transaction, $source, $eventId, $payloadHash): PaymentAttempt {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->with(['items', 'serviceItems'])->lockForUpdate()->findOrFail($order->id);
            /** @var PaymentAttempt|null $attempt */
            $attempt = PaymentAttempt::query()->where('order_id', $lockedOrder->id)->lockForUpdate()->first();
            if ($attempt === null || ! hash_equals($attempt->provider, $provider)) {
                throw new PaymentConfirmationRejected('No matching local payment attempt exists.');
            }
            $this->assertLockedTransaction($lockedOrder, $attempt, $transaction);

            if (PaymentWebhookEvent::query()->where('provider', $provider)->where('external_event_id', $eventId)->exists()) {
                return $attempt;
            }

            if ($this->isTerminalRegression($attempt->status, $transaction->state)) {
                PaymentWebhookEvent::create([
                    'provider' => $provider,
                    'external_event_id' => $eventId,
                    'order_id' => $lockedOrder->id,
                    'payload_sha256' => $payloadHash,
                    'outcome' => 'ignored_terminal_state',
                    'received_at' => now(),
                    'processed_at' => now(),
                ]);

                return $attempt;
            }

            $metadata = is_array($attempt->metadata_encrypted) ? $attempt->metadata_encrypted : [];
            $metadata['provider_state'] = $transaction->state->value;
            $metadata['provider_external_status'] = $transaction->externalStatus;
            $metadata['payment_product_type'] = $transaction->paymentProductType;
            $metadata['provider_updated_at'] = $transaction->updatedAt;

            $authorizedAt = $attempt->authorized_at;
            if ($transaction->state === ProviderPaymentState::Authorized && $authorizedAt === null) {
                $authorizedAt = now();
            }
            $authorizationExpiresAt = $attempt->authorization_expires_at;
            if ($transaction->state === ProviderPaymentState::Authorized && $authorizedAt !== null) {
                $authorizationExpiresAt = $this->expiryPolicy->expiresAt($transaction, $authorizedAt);
            }

            $paymentStatus = $this->paymentStatus($transaction->state, $attempt->status);
            $capturedAmount = $attempt->captured_amount_minor;
            $refundedAmount = $attempt->refunded_amount_minor;
            if ($transaction->state === ProviderPaymentState::Paid && $capturedAmount === 0) {
                $capturedAmount = $transaction->amountMinor;
            }
            if ($transaction->state === ProviderPaymentState::Refunded) {
                $capturedAmount = max($capturedAmount, $transaction->amountMinor);
                $refundedAmount = $capturedAmount;
            }

            $outcome = match ($transaction->state) {
                ProviderPaymentState::Pending => 'pending',
                ProviderPaymentState::Authorized => 'authorized',
                ProviderPaymentState::PartiallyPaid => $lockedOrder->goods_ready_at === null ? 'partial_capture_without_goods_ready' : 'partially_paid',
                ProviderPaymentState::Paid => $lockedOrder->goods_ready_at === null ? 'paid_without_goods_ready' : 'paid',
                ProviderPaymentState::Voided => 'voided',
                ProviderPaymentState::Failed => 'failed',
                ProviderPaymentState::PartiallyRefunded => 'partially_refunded',
                ProviderPaymentState::Refunded => 'refunded',
            };

            $attempt->update([
                'provider_reference' => $transaction->providerReference,
                'provider_session_reference' => $transaction->providerSessionReference,
                'status' => $paymentStatus,
                'metadata_encrypted' => $metadata,
                'payment_product_type' => $transaction->paymentProductType ?: $attempt->payment_product_type,
                'card_last_four' => $transaction->cardLastFour ?: $attempt->card_last_four,
                'authorized_at' => $authorizedAt,
                'authorization_expires_at' => $authorizationExpiresAt,
                'captured_amount_minor' => $capturedAmount,
                'refunded_amount_minor' => $refundedAmount,
                'captured_at' => in_array($transaction->state, [ProviderPaymentState::PartiallyPaid, ProviderPaymentState::Paid], true) && $attempt->captured_at === null ? now() : $attempt->captured_at,
                'voided_at' => $transaction->state === ProviderPaymentState::Voided && $attempt->voided_at === null ? now() : $attempt->voided_at,
                'refunded_at' => in_array($transaction->state, [ProviderPaymentState::PartiallyRefunded, ProviderPaymentState::Refunded], true) ? now() : $attempt->refunded_at,
                'completed_at' => in_array($transaction->state, [ProviderPaymentState::Paid, ProviderPaymentState::Voided, ProviderPaymentState::Failed, ProviderPaymentState::Refunded], true) ? ($attempt->completed_at ?? now()) : $attempt->completed_at,
            ]);

            $previousOrderStatus = $lockedOrder->status;
            $newOrderStatus = match ($transaction->state) {
                ProviderPaymentState::PartiallyPaid, ProviderPaymentState::Paid => OrderStatus::Paid,
                ProviderPaymentState::Refunded => OrderStatus::Refunded,
                default => $lockedOrder->status,
            };
            $lockedOrder->update(['payment_status' => $paymentStatus, 'status' => $newOrderStatus]);

            if (in_array($transaction->state, [ProviderPaymentState::PartiallyPaid, ProviderPaymentState::Paid], true) && $previousOrderStatus !== OrderStatus::Paid) {
                $lockedOrder->statusHistory()->create([
                    'from_status' => $previousOrderStatus,
                    'to_status' => OrderStatus::Paid,
                    'metadata' => [
                        'source' => $source,
                        'provider' => $provider,
                        'provider_reference' => $transaction->providerReference,
                        'unexpected_capture' => $lockedOrder->goods_ready_at === null,
                        'partial' => $transaction->state === ProviderPaymentState::PartiallyPaid,
                    ],
                    'occurred_at' => now(),
                ]);
                $payload = $this->paidOrderPayload->for($lockedOrder);
                $this->outbox->forOrder($lockedOrder, 'notion.order_paid', "notion:order-paid:{$lockedOrder->id}", $payload);
                $this->outbox->forOrder($lockedOrder, 'telegram.order_paid', "telegram:order-paid:{$lockedOrder->id}", $payload);
            }

            PaymentWebhookEvent::create([
                'provider' => $provider,
                'external_event_id' => $eventId,
                'order_id' => $lockedOrder->id,
                'payload_sha256' => $payloadHash,
                'outcome' => $outcome,
                'received_at' => now(),
                'processed_at' => now(),
            ]);

            return $attempt->refresh();
        }, 3);
    }

    private function paymentStatus(ProviderPaymentState $state, PaymentStatus $current): PaymentStatus
    {
        return match ($state) {
            ProviderPaymentState::Pending => $current,
            ProviderPaymentState::Authorized => PaymentStatus::Authorized,
            ProviderPaymentState::PartiallyPaid => PaymentStatus::PartiallyPaid,
            ProviderPaymentState::Paid => PaymentStatus::Paid,
            ProviderPaymentState::Voided => PaymentStatus::Voided,
            ProviderPaymentState::Failed => PaymentStatus::Failed,
            ProviderPaymentState::PartiallyRefunded => PaymentStatus::PartiallyRefunded,
            ProviderPaymentState::Refunded => PaymentStatus::Refunded,
        };
    }

    private function assertTransaction(Order $order, PaymentTransactionResult $transaction): void
    {
        if (! hash_equals($order->public_reference, $transaction->merchantReference)
            || $transaction->amountMinor !== $order->total_minor
            || ! hash_equals($order->currency, $transaction->currency)) {
            throw new PaymentConfirmationRejected('Provider transaction does not match the immutable order snapshot.');
        }
    }

    private function assertLockedTransaction(Order $order, PaymentAttempt $attempt, PaymentTransactionResult $transaction): void
    {
        $this->assertTransaction($order, $transaction);
        if ($attempt->provider_session_reference !== null && ! hash_equals($attempt->provider_session_reference, $transaction->providerSessionReference)) {
            throw new PaymentConfirmationRejected('Provider transaction belongs to a different checkout session.');
        }
        if ($attempt->provider_reference !== null && ! hash_equals($attempt->provider_reference, $transaction->providerReference)) {
            throw new PaymentConfirmationRejected('Provider transaction reference changed unexpectedly.');
        }
    }

    private function isTerminalRegression(PaymentStatus $local, ProviderPaymentState $remote): bool
    {
        return match ($local) {
            PaymentStatus::Refunded => $remote !== ProviderPaymentState::Refunded,
            PaymentStatus::PartiallyRefunded => ! in_array($remote, [ProviderPaymentState::PartiallyRefunded, ProviderPaymentState::Refunded], true),
            PaymentStatus::Paid => ! in_array($remote, [ProviderPaymentState::Paid, ProviderPaymentState::PartiallyRefunded, ProviderPaymentState::Refunded], true),
            PaymentStatus::PartiallyPaid => ! in_array($remote, [ProviderPaymentState::PartiallyPaid, ProviderPaymentState::Paid, ProviderPaymentState::PartiallyRefunded, ProviderPaymentState::Refunded], true),
            PaymentStatus::Voided => $remote !== ProviderPaymentState::Voided,
            PaymentStatus::Failed => $remote !== ProviderPaymentState::Failed,
            default => false,
        };
    }
}
