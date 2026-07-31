<?php

namespace App\Modules\NdiApi\Data;

final readonly class NdiC1OrderResultLine
{
    public function __construct(
        public string $lineId,
        public string $buyersArticleId,
        public string $errorCode,
        public ?string $externalOrderReference,
        public int $confirmedQuantity,
        public ?string $deliveryDate,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->errorCode === '0';
    }
}
