<?php

namespace App\Modules\Cart;

use App\Modules\Cart\Data\CartIssue;
use App\Modules\Cart\Data\CartLine;
use App\Modules\Cart\Data\CartSnapshot;
use App\Modules\Catalog\ReadModel\CatalogReadService;
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

            $price = $this->pricing->forItem($item);
            if ($price === null) {
                $issues[] = new CartIssue($sku, $quantity, 'price_unavailable');

                continue;
            }

            if ($item->stock->quantity < $quantity) {
                $issues[] = new CartIssue($sku, $quantity, 'stock_changed');

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

        return new CartSnapshot($lines, $issues, new Money($total, $currency));
    }
}
