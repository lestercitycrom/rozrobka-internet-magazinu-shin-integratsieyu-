<?php

namespace App\Modules\Payments;

use App\Modules\Orders\Models\Order;

final class PaidOrderPayload
{
    /** @return array<string, mixed> */
    public function for(Order $order): array
    {
        $order->loadMissing(['items', 'serviceItems']);
        $productLineCount = $order->items->count();
        $serviceLineCount = $order->serviceItems->count();

        return [
            'order_id' => $order->id,
            'reference' => $order->public_reference,
            'amount_minor' => $order->total_minor,
            'currency' => $order->currency,
            'product_item_count' => (int) $order->items->sum('quantity'),
            'service_item_count' => (int) $order->serviceItems->sum('quantity'),
            'product_line_count' => $productLineCount,
            'service_line_count' => $serviceLineCount,
            'line_count' => $productLineCount + $serviceLineCount,
            'customer' => [
                'name' => $order->customer_name,
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
            ],
            'items' => $order->items->map(fn ($item): array => [
                'sku' => $item->external_sku,
                'title' => $item->product_title,
                'quantity' => $item->quantity,
                'unit_price_minor' => $item->unit_price_minor,
            ])->all(),
        ];
    }
}
