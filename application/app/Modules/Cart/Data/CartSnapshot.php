<?php

namespace App\Modules\Cart\Data;

use App\Modules\Pricing\ValueObjects\Money;

final readonly class CartSnapshot
{
    /**
     * @param  list<CartLine>  $lines
     * @param  list<CartIssue>  $issues
     * @param  list<CartServiceLine>  $serviceLines
     * @param  list<CartServiceOption>  $serviceOptions
     * @param  list<CartServiceIssue>  $serviceIssues
     */
    public function __construct(
        public array $lines,
        public array $issues,
        public Money $total,
        public array $serviceLines = [],
        public array $serviceOptions = [],
        public array $serviceIssues = [],
    ) {}

    public function isCheckoutReady(): bool
    {
        return $this->lines !== []
            && $this->issues === []
            && $this->serviceIssues === []
            && $this->total->minor > 0
            && collect($this->lines)->every(fn (CartLine $line): bool => $line->price->money->minor > 0 && $line->total()->minor > 0);
    }

    public function quantity(): int
    {
        return array_sum(array_map(fn (CartLine $line): int => $line->quantity, $this->lines));
    }

    public function productsTotal(): Money
    {
        return new Money(array_sum(array_map(fn (CartLine $line): int => $line->total()->minor, $this->lines)), $this->total->currency);
    }

    public function servicesTotal(): Money
    {
        return new Money(array_sum(array_map(fn (CartServiceLine $line): int => $line->total()->minor, $this->serviceLines)), $this->total->currency);
    }
}
