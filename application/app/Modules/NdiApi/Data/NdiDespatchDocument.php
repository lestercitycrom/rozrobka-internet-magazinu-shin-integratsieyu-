<?php

namespace App\Modules\NdiApi\Data;

final readonly class NdiDespatchDocument
{
    /** @param list<NdiTrackingLine> $tracking */
    public function __construct(
        public string $documentNumber,
        public ?string $arrivalDate,
        public array $tracking,
    ) {}
}
