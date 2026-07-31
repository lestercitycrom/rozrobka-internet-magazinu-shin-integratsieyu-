<?php

namespace App\Modules\NdiApi;

use App\Modules\Orders\Models\Order;

final class OrderLineFingerprint
{
    public function forOrder(Order $order): string
    {
        $lines = $order->relationLoaded('items') ? $order->items : $order->items()->get();
        $normalized = $lines
            ->map(fn ($line): array => [
                'sku' => (string) $line->external_sku,
                'quantity' => (int) $line->quantity,
            ])
            ->sortBy([['sku', 'asc'], ['quantity', 'asc']])
            ->values()
            ->all();

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
