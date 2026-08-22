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
    ) {}

    public function execute(Order $order, string $providerReference, string $source): PaymentAttempt
    {
        PaymentBoundaryGuard::assertEnabled();
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

            $duplicate = PaymentWebhookEvent::query()
                ->where('provider', $provider)
                ->where('external_event_id', $eventId)
                ->exists();
            if ($duplicate) {
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

            $outcome = match ($transaction->state) {
                ProviderPaymentState::Pending => 'pending',
                ProviderPaymentState::Authorized => 'authorized',
                ProviderPaymentState::Paid => $lockedOrder->goods_ready_at === null ? 'paid_without_goods_ready' : 'paid',
                ProviderPaymentState::Voided => 'voided',
                ProviderPaymentState::Failed => 'failed',
            };

            $attempt->update([
                'provider_reference' => $transaction->providerReference,
                'provider_session_reference' => $transaction->providerSessionReference,
                'status' => match ($transaction->state) {
                    ProviderPaymentState::Pending => $attempt->status,
                    ProviderPaymentState::Authorized => PaymentStatus::Authorized,
                    ProviderPaymentState::Paid => PaymentStatus::Paid,
                    ProviderPaymentState::Voided => PaymentStatus::Voided,
                    ProviderPaymentState::Failed => PaymentStatus::Failed,
                },
                'metadata_encrypted' => $metadata,
                'authorized_at' => $transaction->state === ProviderPaymentState::Authorized && $attempt->authorized_at === null ? now() : $attempt->authorized_at,
                'captured_at' => $transaction->state === ProviderPaymentState::Paid && $attempt->captured_at === null ? now() : $attempt->captured_at,
                'voided_at' => $transaction->state === ProviderPaymentState::Voided && $attempt->voided_at === null ? now() : $attempt->voided_at,
                'completed_at' => in_array($transaction->state, [ProviderPaymentState::Paid, ProviderPaymentState::Voided, ProviderPaymentState::Failed], true) ? ($attempt->completed_at ?? now()) : $attempt->completed_at,
            ]);

            $previousOrderStatus = $lockedOrder->status;
            $lockedOrder->update([
                'payment_status' => match ($transaction->state) {
                    ProviderPaymentState::Pending => $lockedOrder->payment_status,
                    ProviderPaymentState::Authorized => PaymentStatus::Authorized,
                    ProviderPaymentState::Paid => PaymentStatus::Paid,
                    ProviderPaymentState::Voided => PaymentStatus::Voided,
                    ProviderPaymentState::Failed => PaymentStatus::Failed,
                },
                'status' => $transaction->state === ProviderPaymentState::Paid ? OrderStatus::Paid : $lockedOrder->status,
            ]);

            if ($transaction->state === ProviderPaymentState::Paid && $previousOrderStatus !== OrderStatus::Paid) {
                $lockedOrder->statusHistory()->create([
                    'from_status' => $previousOrderStatus,
                    'to_status' => OrderStatus::Paid,
                    'metadata' => [
                        'source' => $source,
                        'provider' => $provider,
                        'provider_reference' => $transaction->providerReference,
                        'unexpected_capture' => $lockedOrder->goods_ready_at === null,
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
        if ($attempt->provider_session_reference !== null
            && ! hash_equals($attempt->provider_session_reference, $transaction->providerSessionReference)) {
            throw new PaymentConfirmationRejected('Provider transaction belongs to a different checkout session.');
        }
        if ($attempt->provider_reference !== null
            && ! hash_equals($attempt->provider_reference, $transaction->providerReference)) {
            throw new PaymentConfirmationRejected('Provider transaction reference changed unexpectedly.');
        }
    }

    private function isTerminalRegression(PaymentStatus $local, ProviderPaymentState $remote): bool
    {
        return match ($local) {
            PaymentStatus::Paid => $remote !== ProviderPaymentState::Paid,
            PaymentStatus::Voided => $remote !== ProviderPaymentState::Voided,
            PaymentStatus::Failed => $remote !== ProviderPaymentState::Failed,
            PaymentStatus::Refunded => true,
            default => false,
        };
    }
}
