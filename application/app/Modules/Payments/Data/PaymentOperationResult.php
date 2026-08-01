<?php

namespace App\Modules\Payments\Data;

final readonly class PaymentOperationResult
{
    public function __construct(public string $providerReference) {}
}
