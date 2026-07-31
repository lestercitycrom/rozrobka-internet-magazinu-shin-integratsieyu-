<?php

namespace App\Modules\Outbox;

use App\Modules\Orders\Models\Order;
use App\Modules\Outbox\Enums\OutboxStatus;
use App\Modules\Outbox\Models\OutboxMessage;

final class OutboxPublisher
{
    /** @param array<string, mixed> $payload */
    public function forOrder(Order $order, string $type, string $idempotencyKey, array $payload): OutboxMessage
    {
        return OutboxMessage::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'type' => $type,
                'aggregate_type' => 'order',
                'aggregate_id' => (string) $order->getKey(),
                'payload_encrypted' => $payload,
                'status' => OutboxStatus::Pending,
                'available_at' => now(),
            ],
        );
    }
}
