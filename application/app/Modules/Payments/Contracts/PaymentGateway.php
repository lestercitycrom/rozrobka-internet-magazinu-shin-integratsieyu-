<?php

namespace App\Modules\Payments\Contracts;

use App\Modules\Payments\Data\PaymentStartRequest;
use App\Modules\Payments\Data\PaymentStartResult;

interface PaymentGateway
{
    public function start(PaymentStartRequest $request): PaymentStartResult;
}
