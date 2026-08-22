<?php

namespace App\Modules\Cart;

use App\Modules\Cart\Data\CartIssue;
use App\Modules\Cart\Data\CartLine;
use App\Modules\Cart\Data\CartServiceIssue;
use App\Modules\Cart\Data\CartServiceLine;
use App\Modules\Cart\Data\CartServiceOption;
use App\Modules\Cart\Data\CartSnapshot;
use App\Modules\Catalog\ReadModel\CatalogReadService;
use App\Modules\Commerce\Enums\ServiceAddonBillingMode;
use App\Modules\Commerce\Models\ServiceAddon;
use App\Modules\Pricing\SellingPriceService;
use App\Modules\Pricing\ValueObjects\Money;

final class CartResolver
{
    public function __construct(
        private readonly SessionCart $cart,
        private readonly CatalogReadService $catalog,
        private readonly SellingPriceService $pricing,
    ) {}

    public function resolve(): CartSnapshot
    {
        $lines = [];
        $issues = [];
        $currency = 'NOK';
        $total = 0;

        foreach ($this->cart->items() as $sku => $quantity) {
            $item = $this->catalog->findPublicSku($sku);
            if ($item === null) {
                $issues[] = new CartIssue($sku, $quantity, 'product_unavailable');

                continue;
            }

            if (! $item->stock->isFresh()) {
                $issues[] = new CartIssue($sku, $quantity, 'stock_stale');

                continue;
            }

            $price = $this->pricing->forItem($item);
            if ($price === null) {
                $issues[] = new CartIssue($sku, $quantity, 'price_unavailable');

                continue;
            }

            if ($item->stock->quantity < $quantity) {
                $issues[] = new CartIssue($sku, $quantity, 'stock_changed', $item->stock->quantity);

                continue;
            }

            if ($item->isIntegrationTest && $quantity !== (int) config('ndi_api.sandbox.quantity', 2)) {
                $issues[] = new CartIssue($sku, $quantity, 'sandbox_quantity_required', (int) config('ndi_api.sandbox.quantity', 2));

                continue;
            }

            if ($lines !== [] && $price->money->currency !== $currency) {
                $issues[] = new CartIssue($sku, $quantity, 'currency_mismatch');

                continue;
            }

            $currency = $price->money->currency;
            $line = new CartLine($item, $price, $quantity);
            $lines[] = $line;
            $total += $line->total()->minor;
        }

        $profiles = collect($lines)->pluck('item.integrationProfile')->unique()->values();
        if ($profiles->count() > 1) {
            foreach ($lines as $line) {
                $issues[] = new CartIssue($line->item->sku, $line->quantity, 'mixed_integration_profiles');
            }
        }

        $selectedCodes = $this->cart->serviceAddons();
        $selectedAddons = ServiceAddon::query()
            ->whereIn('code', $selectedCodes)
            ->get()
            ->keyBy('code');
        $serviceLines = [];
        $serviceIssues = [];

        foreach ($selectedCodes as $code) {
            /** @var ServiceAddon|null $addon */
            $addon = $selectedAddons->get($code);
            if ($addon === null || ! $this->isAvailable($addon)) {
                $serviceIssues[] = new CartServiceIssue($code, 'unavailable');

                continue;
            }

            $quantity = $this->applicableQuantity($addon, $lines);
            if ($quantity === 0) {
                $serviceIssues[] = new CartServiceIssue($code, 'not_applicable');

                continue;
            }
            if ($lines !== [] && $addon->currency !== $currency) {
                $serviceIssues[] = new CartServiceIssue($code, 'currency_mismatch');

                continue;
            }

            $billedQuantity = $addon->billing_mode === ServiceAddonBillingMode::PerOrder ? 1 : $quantity;
            $line = $this->serviceLine($addon, $billedQuantity);
            $serviceLines[] = $line;
            $total += $line->total()->minor;
        }

        $selectedValidCodes = collect($serviceLines)->pluck('code')->all();
        $serviceOptions = ServiceAddon::query()
            ->available()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (ServiceAddon $addon) use ($lines, $selectedValidCodes): ?CartServiceOption {
                $quantity = $this->applicableQuantity($addon, $lines);
                if ($quantity === 0) {
                    return null;
                }

                return new CartServiceOption(
                    code: $addon->code,
                    titleNb: $addon->title_nb,
                    titleRu: $addon->title_ru,
                    billingMode: $addon->billing_mode,
                    unitPrice: new Money($addon->price_minor, $addon->currency),
                    quantity: $addon->billing_mode === ServiceAddonBillingMode::PerOrder ? 1 : $quantity,
                    selected: in_array($addon->code, $selectedValidCodes, true),
                );
            })
            ->filter()
            ->values()
            ->all();

        return new CartSnapshot(
            lines: $lines,
            issues: $issues,
            total: new Money($total, $currency),
            serviceLines: $serviceLines,
            serviceOptions: $serviceOptions,
            serviceIssues: $serviceIssues,
        );
    }

    /** @param list<CartLine> $lines */
    private function applicableQuantity(ServiceAddon $addon, array $lines): int
    {
        return array_sum(array_map(
            fn (CartLine $line): int => $addon->applicability->appliesTo($line->item->productType) ? $line->quantity : 0,
            $lines,
        ));
    }

    private function isAvailable(ServiceAddon $addon): bool
    {
        return $addon->is_active
            && $addon->currency === 'NOK'
            && $addon->price_minor !== null
            && $addon->price_minor > 0;
    }

    private function serviceLine(ServiceAddon $addon, int $quantity): CartServiceLine
    {
        return new CartServiceLine(
            serviceAddonId: $addon->id,
            code: $addon->code,
            titleNb: $addon->title_nb,
            titleRu: $addon->title_ru,
            applicability: $addon->applicability,
            billingMode: $addon->billing_mode,
            unitPrice: new Money($addon->price_minor, $addon->currency),
            quantity: $quantity,
        );
    }
}
