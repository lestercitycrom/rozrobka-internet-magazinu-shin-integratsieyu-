<?php

namespace App\Modules\Payments\Data;

final readonly class PaymentAuthorizationResult
{
    public function __construct(
        public string $providerReference,
        public ?string $redirectUrl = null,
    ) {}
}
