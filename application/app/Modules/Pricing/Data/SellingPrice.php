<?php

namespace App\Modules\Pricing\Data;

use App\Modules\Pricing\ValueObjects\Money;

final readonly class SellingPrice
{
    public function __construct(
        public Money $money,
        public Money $sourceMoney,
        public int $ruleId,
        public string $ruleName,
        public string $sourcePriceListCode,
        public ?string $sourceObservedAt,
        public bool $isSnapshot = false,
    ) {}
}
