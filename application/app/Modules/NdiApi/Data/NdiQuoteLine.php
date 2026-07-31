<?php

namespace App\Modules\NdiApi\Data;

final readonly class NdiQuoteLine
{
    public function __construct(
        public string $lineId,
        public string $sku,
        public int $requestedQuantity,
        public int $availableQuantity,
        public ?string $deliveryDate,
    ) {}
}
