<?php

namespace App\Modules\Payments\Data;

use App\Modules\Payments\Enums\ProviderPaymentState;

final readonly class PaymentTransactionResult
{
    public function __construct(
        public string $providerReference,
        public string $providerSessionReference,
        public string $merchantReference,
        public ProviderPaymentState $state,
        public int $amountMinor,
        public string $currency,
        public string $externalStatus,
        public ?string $paymentProductType = null,
        public ?string $updatedAt = null,
    ) {}
}
