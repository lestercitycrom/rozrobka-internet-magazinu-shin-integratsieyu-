<?php

namespace App\Modules\Cart\Data;

use App\Modules\Commerce\Enums\ServiceAddonBillingMode;
use App\Modules\Pricing\ValueObjects\Money;

final readonly class CartServiceOption
{
    public function __construct(
        public string $code,
        public string $titleNb,
        public string $titleRu,
        public ServiceAddonBillingMode $billingMode,
        public Money $unitPrice,
        public int $quantity,
        public bool $selected,
    ) {}

    public function title(string $locale): string
    {
        return $locale === 'ru' ? $this->titleRu : $this->titleNb;
    }

    public function total(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }
}
