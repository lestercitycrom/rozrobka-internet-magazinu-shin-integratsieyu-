<?php

namespace App\Modules\Payments;

use App\Modules\Catalog\ReadModel\CatalogReadService;
use App\Modules\Commerce\Models\ServiceAddon;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Contracts\OrderPaymentReadiness;
use App\Modules\Payments\Exceptions\PaymentOperationRejected;
use App\Modules\Pricing\SellingPriceService;

final class ConfirmCurrentOrderPaymentReadiness implements OrderPaymentReadiness
{
    public function __construct(
        private readonly CatalogReadService $catalog,
        private readonly SellingPriceService $pricing,
    ) {}

    public function assertReady(Order $order): void
    {
        $order->loadMissing(['items', 'serviceItems']);
        if ($order->items->isEmpty()) {
            throw new PaymentOperationRejected('The order has no supplier products to validate.');
        }

        $currentTotal = 0;
        foreach ($order->items as $line) {
            $item = $this->catalog->findPublicSku((string) $line->external_sku);
            if ($item === null || $item->stock->quantity < $line->quantity) {
                throw new PaymentOperationRejected('Current product availability must be confirmed before payment.');
            }

            $price = $this->pricing->forItem($item);
            $expectedLineTotal = $price?->money->minor === null ? null : $price->money->minor * $line->quantity;
            if ($price === null
                || ! hash_equals($order->currency, $price->money->currency)
                || ! hash_equals($line->currency, $price->money->currency)
                || $line->unit_price_minor !== $price->money->minor
                || $line->line_total_minor !== $expectedLineTotal) {
                throw new PaymentOperationRejected('The current selling price differs from the accepted order snapshot.');
            }

            $currentTotal += $expectedLineTotal;
        }

        $addons = ServiceAddon::query()
            ->whereIn('code', $order->serviceItems->pluck('service_code'))
            ->get()
            ->keyBy('code');
        foreach ($order->serviceItems as $line) {
            /** @var ServiceAddon|null $addon */
            $addon = $addons->get($line->service_code);
            $expectedLineTotal = $addon?->price_minor === null ? null : $addon->price_minor * $line->quantity;
            if ($addon === null
                || ! $addon->is_active
                || $addon->price_minor < 1
                || ! hash_equals($order->currency, $addon->currency)
                || ! hash_equals($line->currency, $addon->currency)
                || $line->unit_price_minor !== $addon->price_minor
                || $line->line_total_minor !== $expectedLineTotal) {
                throw new PaymentOperationRejected('The current service price differs from the accepted order snapshot.');
            }

            $currentTotal += $expectedLineTotal;
        }

        if ($currentTotal < 1 || $order->subtotal_minor !== $currentTotal || $order->total_minor !== $currentTotal) {
            throw new PaymentOperationRejected('The current order total differs from the accepted order snapshot.');
        }
    }
}
