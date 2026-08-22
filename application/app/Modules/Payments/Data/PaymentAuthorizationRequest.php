<?php

namespace App\Modules\Payments\Data;

final readonly class PaymentAuthorizationRequest
{
    public function __construct(
        public string $orderId,
        public string $orderReference,
        public int $amountMinor,
        public string $currency,
        public string $idempotencyKey,
        public string $returnUrl,
        public string $callbackUrl = '',
        public ?string $termsUrl = null,
        public string $customerName = '',
        public string $customerEmail = '',
        public string $customerPhone = '',
        /** @var list<array{id:string,line_id:string,description:string,quantity:int,amount:int,vat_amount:int,vat:int}> */
        public array $items = [],
    ) {}
}
