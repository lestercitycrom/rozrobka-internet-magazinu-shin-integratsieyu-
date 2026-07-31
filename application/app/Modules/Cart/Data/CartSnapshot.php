<?php

namespace App\Modules\Cart\Data;

use App\Modules\Pricing\ValueObjects\Money;

final readonly class CartSnapshot
{
    /** @param list<CartLine> $lines @param list<CartIssue> $issues */
    public function __construct(public array $lines, public array $issues, public Money $total) {}

    public function isCheckoutReady(): bool
    {
        return $this->lines !== [] && $this->issues === [];
    }

    public function quantity(): int
    {
        return array_sum(array_map(fn (CartLine $line): int => $line->quantity, $this->lines));
    }
}
