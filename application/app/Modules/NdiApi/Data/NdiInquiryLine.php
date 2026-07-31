<?php

namespace App\Modules\NdiApi\Data;

use InvalidArgumentException;

final readonly class NdiInquiryLine
{
    public function __construct(
        public string $lineId,
        public string $sku,
        public int $quantity,
    ) {
        if ($this->lineId === '' || mb_strlen($this->lineId) > 50) {
            throw new InvalidArgumentException('NDI line ID must contain 1 to 50 characters.');
        }

        if ($this->sku === '' || mb_strlen($this->sku) > 100) {
            throw new InvalidArgumentException('NDI SKU must contain 1 to 100 characters.');
        }

        if ($this->quantity < 1) {
            throw new InvalidArgumentException('NDI requested quantity must be positive.');
        }
    }
}
