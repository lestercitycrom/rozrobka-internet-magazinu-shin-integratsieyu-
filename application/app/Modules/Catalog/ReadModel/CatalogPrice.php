<?php

namespace App\Modules\Catalog\ReadModel;

use App\Modules\Catalog\Enums\TaxMode;

/** A source price, deliberately without a derived selling-price policy. */
final readonly class CatalogPrice
{
    public function __construct(
        public string $amount,
        public string $currency,
        public TaxMode $taxMode,
        public string $observedAt,
        public string $priceListCode,
    ) {}
}
