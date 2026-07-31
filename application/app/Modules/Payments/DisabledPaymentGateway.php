<?php

namespace App\Modules\Payments;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\PaymentStartRequest;
use App\Modules\Payments\Data\PaymentStartResult;
use App\Modules\Payments\Exceptions\PaymentsDisabled;

final class DisabledPaymentGateway implements PaymentGateway
{
    public function start(PaymentStartRequest $request): PaymentStartResult
    {
        throw new PaymentsDisabled('Payment initiation is disabled until a provider contract is configured.');
    }
}
