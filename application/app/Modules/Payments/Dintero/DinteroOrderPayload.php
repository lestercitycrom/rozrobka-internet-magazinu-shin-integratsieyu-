<?php

namespace App\Modules\Payments\Dintero;

use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Exceptions\PaymentOperationRejected;

final class DinteroOrderPayload
{
    /** @return list<array{id:string,line_id:string,description:string,quantity:int,amount:int,vat_amount:int,vat:int}> */
    public function authorizationItems(Order $order): array
    {
        $order->loadMissing(['items', 'serviceItems']);
        $vat = (int) config('payments.dintero.vat_percent', 25);
        if ($vat < 0 || $vat > 100) {
            throw new PaymentOperationRejected('Dintero VAT percentage is outside the supported range.');
        }

        $items = [];
        foreach ($order->items as $item) {
            $items[] = $this->authorizationLine(
                id: (string) $item->external_sku,
                lineId: 'product-'.$item->id,
                description: (string) $item->product_title,
                quantity: (int) $item->quantity,
                amount: (int) $item->line_total_minor,
                vat: $vat,
            );
        }
        foreach ($order->serviceItems as $item) {
            $items[] = $this->authorizationLine(
                id: 'service-'.(string) $item->service_code,
                lineId: 'service-'.$item->id,
                description: (string) ($order->locale === 'ru' ? $item->title_ru : $item->title_nb),
                quantity: (int) $item->quantity,
                amount: (int) $item->line_total_minor,
                vat: $vat,
            );
        }

        if ($items === [] || array_sum(array_column($items, 'amount')) !== $order->total_minor) {
            throw new PaymentOperationRejected('Dintero item totals do not match the immutable order total.');
        }

        return $items;
    }

    /** @return list<array{line_id:string,quantity:int,amount:int}> */
    public function operationItems(Order $order): array
    {
        return array_map(
            static fn (array $item): array => [
                'line_id' => $item['line_id'],
                'quantity' => $item['quantity'],
                'amount' => $item['amount'],
            ],
            $this->authorizationItems($order),
        );
    }

    /**
     * @param  array<string, int|string>  $quantities  keyed by Dintero line_id
     * @return list<array{line_id:string,quantity:int,amount:int}>
     */
    public function operationItemsForQuantities(Order $order, array $quantities): array
    {
        $selected = [];
        foreach ($this->authorizationItems($order) as $item) {
            $quantity = filter_var($quantities[$item['line_id']] ?? 0, FILTER_VALIDATE_INT);
            if ($quantity === false || $quantity < 0 || $quantity > $item['quantity']) {
                throw new PaymentOperationRejected('Payment operation quantity is outside the immutable order line.');
            }
            if ($quantity === 0) {
                continue;
            }
            if ($item['amount'] % $item['quantity'] !== 0) {
                throw new PaymentOperationRejected('Payment operation cannot split a non-divisible order line safely.');
            }
            $selected[] = [
                'line_id' => $item['line_id'],
                'quantity' => $quantity,
                'amount' => intdiv($item['amount'], $item['quantity']) * $quantity,
            ];
        }

        if ($selected === []) {
            throw new PaymentOperationRejected('Payment operation must include at least one order line.');
        }

        return $selected;
    }

    public function operationAmount(array $items): int
    {
        $amount = array_sum(array_map(static fn (array $item): int => (int) ($item['amount'] ?? 0), $items));
        if ($amount < 1) {
            throw new PaymentOperationRejected('Payment operation amount must be positive.');
        }

        return $amount;
    }

    /** @return array{id:string,line_id:string,description:string,quantity:int,amount:int,vat_amount:int,vat:int} */
    private function authorizationLine(string $id, string $lineId, string $description, int $quantity, int $amount, int $vat): array
    {
        if ($quantity < 1 || $amount < 1) {
            throw new PaymentOperationRejected('Dintero line quantity and amount must be positive.');
        }

        return [
            'id' => mb_substr($id, 0, 255),
            'line_id' => mb_substr($lineId, 0, 255),
            'description' => mb_substr(trim($description), 0, 255),
            'quantity' => $quantity,
            'amount' => $amount,
            'vat_amount' => $vat === 0 ? 0 : (int) round($amount * $vat / (100 + $vat)),
            'vat' => $vat,
        ];
    }
}
