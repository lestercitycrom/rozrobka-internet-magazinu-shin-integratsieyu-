<?php

namespace App\Modules\Payments\Data;

final readonly class PaymentOperationRequest
{
    public function __construct(
        public string $providerReference,
        public int $amountMinor,
        public string $currency,
        public string $idempotencyKey,
        public string $operationReference = '',
        /** @var list<array{line_id:string,quantity:int,amount:int}> */
        public array $items = [],
    ) {}
}
