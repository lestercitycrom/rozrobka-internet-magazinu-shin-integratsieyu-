<?php

namespace App\Modules\NdiApi\Data;

final readonly class NdiTrackingLine
{
    public function __construct(
        public string $trackingNumber,
        public ?string $trackingUrl,
        public ?string $globalStatus,
        public ?string $localStatus,
        public ?string $occurredAt,
    ) {}
}
