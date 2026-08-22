<?php

namespace App\Modules\Payments\Data;

use App\Modules\Payments\Enums\ProviderPaymentState;

final readonly class PaymentAuthorizationResult
{
    public function __construct(
        public string $providerReference,
        public ?string $redirectUrl = null,
        public ProviderPaymentState $state = ProviderPaymentState::Authorized,
        public ?string $providerSessionReference = null,
    ) {}
}
