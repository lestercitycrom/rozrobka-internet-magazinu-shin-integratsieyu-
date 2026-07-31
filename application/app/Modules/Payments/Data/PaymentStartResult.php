<?php

namespace App\Modules\Payments\Data;

final readonly class PaymentStartResult
{
    public function __construct(public string $providerReference, public string $redirectUrl) {}
}
