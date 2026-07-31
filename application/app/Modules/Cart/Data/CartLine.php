<?php

namespace App\Modules\Cart\Data;

use App\Modules\Catalog\ReadModel\CatalogItem;
use App\Modules\Pricing\Data\SellingPrice;
use App\Modules\Pricing\ValueObjects\Money;

final readonly class CartLine
{
    public function __construct(public CatalogItem $item, public SellingPrice $price, public int $quantity) {}

    public function total(): Money
    {
        return $this->price->money->multiply($this->quantity);
    }
}
