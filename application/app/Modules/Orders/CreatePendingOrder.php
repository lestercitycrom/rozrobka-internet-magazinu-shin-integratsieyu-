<?php

namespace App\Modules\Orders;

use App\Modules\Cart\Data\CartSnapshot;
use App\Modules\Catalog\Models\SupplierSku;
use App\Modules\Orders\Data\CheckoutData;
use App\Modules\Orders\Data\CreateOrderResult;
use App\Modules\Orders\Enums\NdiOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Exceptions\CartNotReady;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreatePendingOrder
{
    public function execute(CheckoutData $data, CartSnapshot $cart): CreateOrderResult
    {
        $existing = Order::query()->where('idempotency_key', $data->idempotencyKey)->first();
        if ($existing !== null) {
            return new CreateOrderResult($existing, false);
        }
        if (! $cart->isCheckoutReady()) {
            throw new CartNotReady('The cart must be resolved without issues before checkout.');
        }

        return DB::transaction(function () use ($data, $cart): CreateOrderResult {
            $existing = Order::query()->where('idempotency_key', $data->idempotencyKey)->first();
            if ($existing !== null) {
                return new CreateOrderResult($existing, false);
            }

            $now = now();
            $order = Order::create([
                'public_reference' => $this->reference(),
                'idempotency_key' => $data->idempotencyKey,
                'locale' => $data->locale,
                'status' => OrderStatus::PendingReview,
                'payment_status' => PaymentStatus::NotStarted,
                'ndi_status' => NdiOrderStatus::NotSubmitted,
                'currency' => $cart->total->currency,
                'subtotal_minor' => $cart->total->minor,
                'total_minor' => $cart->total->minor,
                'fulfilment_method' => $data->fulfilmentMethod,
                'customer_name' => $data->customerName,
                'customer_email' => mb_strtolower($data->customerEmail),
                'customer_phone' => $data->customerPhone,
                'vehicle_registration' => $data->vehicleRegistration,
                'fitment_snapshot' => $data->fitmentSnapshot,
                'customer_note' => $data->customerNote,
                'terms_accepted_at' => $now,
                'privacy_accepted_at' => $now,
                'pricing_snapshot_at' => $now,
                'submitted_at' => $now,
            ]);

            $skuIds = SupplierSku::query()
                ->whereIn('external_sku', collect($cart->lines)->map(fn ($line) => $line->item->sku))
                ->pluck('id', 'external_sku');

            foreach ($cart->lines as $line) {
                $order->items()->create([
                    'supplier_sku_id' => $skuIds->get($line->item->sku),
                    'external_sku' => $line->item->sku,
                    'product_title' => $line->item->title,
                    'quantity' => $line->quantity,
                    'unit_price_minor' => $line->price->money->minor,
                    'line_total_minor' => $line->total()->minor,
                    'currency' => $line->price->money->currency,
                    'product_snapshot' => [
                        'product_type' => $line->item->productType->value,
                        'brand' => $line->item->brand,
                        'model_name' => $line->item->modelName,
                        'tire' => $line->item->tire,
                        'wheel' => $line->item->wheel,
                    ],
                    'pricing_snapshot' => [
                        'rule_id' => $line->price->ruleId,
                        'rule_name' => $line->price->ruleName,
                        'source_price_list_code' => $line->price->sourcePriceListCode,
                        'source_amount_minor' => $line->price->sourceMoney->minor,
                    ],
                    'source_price_observed_at' => $line->price->sourceObservedAt,
                ]);
            }

            foreach ($cart->serviceLines as $line) {
                $order->serviceItems()->create([
                    'service_addon_id' => $line->serviceAddonId,
                    'service_code' => $line->code,
                    'title_nb' => $line->titleNb,
                    'title_ru' => $line->titleRu,
                    'applicability' => $line->applicability,
                    'billing_mode' => $line->billingMode,
                    'quantity' => $line->quantity,
                    'unit_price_minor' => $line->unitPrice->minor,
                    'line_total_minor' => $line->total()->minor,
                    'currency' => $line->unitPrice->currency,
                    'service_snapshot' => [
                        'code' => $line->code,
                        'title_nb' => $line->titleNb,
                        'title_ru' => $line->titleRu,
                        'applicability' => $line->applicability->value,
                        'billing_mode' => $line->billingMode->value,
                        'currency' => $line->unitPrice->currency,
                        'unit_price_minor' => $line->unitPrice->minor,
                        'quantity' => $line->quantity,
                        'line_total_minor' => $line->total()->minor,
                    ],
                ]);
            }

            $order->statusHistory()->create([
                'from_status' => null,
                'to_status' => OrderStatus::PendingReview,
                'note' => null,
                'metadata' => ['source' => 'storefront_checkout'],
                'occurred_at' => $now,
            ]);

            return new CreateOrderResult($order, true);
        }, 3);
    }

    private function reference(): string
    {
        do {
            $reference = 'ET-'.Str::upper(Str::random(14));
        } while (Order::query()->where('public_reference', $reference)->exists());

        return $reference;
    }
}
