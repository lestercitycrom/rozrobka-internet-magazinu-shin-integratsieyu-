<?php

namespace App\Modules\Pricing\Enums;

enum PricingRuleScope: string
{
    case Global = 'global';
    case ProductType = 'product_type';
    case Brand = 'brand';
    case Sku = 'sku';

    public function specificity(): int
    {
        return match ($this) {
            self::Global => 0,
            self::ProductType => 1,
            self::Brand => 2,
            self::Sku => 3,
        };
    }
}
