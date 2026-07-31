<?php

namespace App\Modules\NdiApi\Data;

final readonly class NdiOrderStatusResult
{
    /** @param list<NdiTrackingLine> $tracking */
    public function __construct(
        public string $externalOrderReference,
        public string $salesStatus,
        public string $documentStatus,
        public ?string $carrier,
        public ?string $carrierProduct,
        public array $tracking,
    ) {}
}
