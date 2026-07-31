<?php

namespace App\Modules\Cart\Data;

final readonly class CartIssue
{
    public function __construct(public string $sku, public int $quantity, public string $reason) {}
}
