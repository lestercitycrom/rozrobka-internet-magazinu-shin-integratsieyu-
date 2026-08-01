<?php

namespace App\Modules\Commerce\Enums;

use App\Modules\Catalog\Enums\ProductType;

enum ServiceAddonApplicability: string
{
    case Tire = 'tire';
    case Wheel = 'wheel';
    case All = 'all';

    public function appliesTo(ProductType $productType): bool
    {
        return $this === self::All || $this->value === $productType->value;
    }
}
