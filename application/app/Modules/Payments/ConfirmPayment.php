<?php

namespace App\Modules\Payments;

use App\Modules\Orders\Enums\NdiOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Outbox\OutboxPublisher;
use App\Modules\Payments\Exceptions\PaymentConfirmationRejected;
use App\Modules\Payments\Models\PaymentWebhookEvent;
use Illuminate\Support\Facades\DB;

final class ConfirmPayment
{
    public function __construct(
        private readonly OutboxPublisher $outbox,
        private readonly PaidOrderPayload $paidOrderPayload,
    ) {}

    public function execute(
        Order $order,
        string $provider,
        string $externalEventId,
        string $providerReference,
        int $amountMinor,
        string $currency,
        string $payloadSha256,
    ): Order {
        $this->validateInput($provider, $externalEventId, $providerReference, $amountMinor, $currency, $payloadSha256);
        PaymentBoundaryGuard::assertOrderAllowed($order);

        return DB::transaction(function () use ($order, $provider, $externalEventId, $providerReference, $amountMinor, $currency, $payloadSha256): Order {
            $duplicate = PaymentWebhookEvent::query()
                ->where('provider', $provider)
                ->where('external_event_id', $externalEventId)
                ->first();
            if ($duplicate !== null) {
                return Order::query()->findOrFail($duplicate->order_id);
            }

            /** @var Order $locked */
            $locked = Order::query()->with(['items', 'serviceItems'])->lockForUpdate()->findOrFail($order->getKey());
            if ($amountMinor !== $locked->total_minor || strtoupper($currency) !== $locked->currency) {
                throw new PaymentConfirmationRejected('Payment amount or currency does not match the order snapshot.');
            }

            if ($locked->payment_status === PaymentStatus::Paid) {
                PaymentWebhookEvent::create([
                    'provider' => $provider,
                    'external_event_id' => $externalEventId,
                    'order_id' => $locked->id,
                    'payload_sha256' => $payloadSha256,
                    'outcome' => 'already_paid',
                    'received_at' => now(),
                    'processed_at' => now(),
                ]);

                return $locked;
            }

            if ($locked->status !== OrderStatus::AwaitingPayment) {
                throw new PaymentConfirmationRejected('The order is not awaiting payment.');
            }

            if ($locked->ndi_status !== NdiOrderStatus::Accepted) {
                throw new PaymentConfirmationRejected('Payment capture requires an accepted NDI order.');
            }

            if ($locked->payment_status !== PaymentStatus::Authorized) {
                throw new PaymentConfirmationRejected('Payment capture requires a prior authorization.');
            }

            PaymentWebhookEvent::create([
                'provider' => $provider,
                'external_event_id' => $externalEventId,
                'order_id' => $locked->id,
                'payload_sha256' => $payloadSha256,
                'outcome' => 'paid',
                'received_at' => now(),
                'processed_at' => now(),
            ]);

            $from = $locked->status;
            $locked->update(['payment_status' => PaymentStatus::Paid, 'status' => OrderStatus::Paid]);
            $locked->statusHistory()->create([
                'from_status' => $from,
                'to_status' => OrderStatus::Paid,
                'note' => null,
                'metadata' => ['source' => 'payment_webhook', 'provider' => $provider, 'provider_reference' => $providerReference],
                'occurred_at' => now(),
            ]);

            $payload = $this->paidOrderPayload->for($locked);

            $this->outbox->forOrder($locked, 'notion.order_paid', "notion:order-paid:{$locked->id}", $payload);
            $this->outbox->forOrder($locked, 'telegram.order_paid', "telegram:order-paid:{$locked->id}", $payload);

            return $locked->refresh();
        }, 3);
    }

    private function validateInput(string $provider, string $eventId, string $reference, int $amount, string $currency, string $hash): void
    {
        if (preg_match('/^[a-z0-9_-]{2,64}$/', $provider) !== 1
            || mb_strlen($eventId) > 255 || $eventId === ''
            || mb_strlen($reference) > 255 || $reference === ''
            || $amount < 0
            || preg_match('/^[A-Z]{3}$/', strtoupper($currency)) !== 1
            || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
            throw new PaymentConfirmationRejected('Invalid normalized payment confirmation.');
        }
    }
}
