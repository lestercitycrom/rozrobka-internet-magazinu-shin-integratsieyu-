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
    ) {}
}
