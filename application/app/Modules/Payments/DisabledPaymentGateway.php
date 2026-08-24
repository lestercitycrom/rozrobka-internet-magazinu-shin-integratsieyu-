<?php

namespace App\Modules\Payments;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\PaymentAuthorizationRequest;
use App\Modules\Payments\Data\PaymentAuthorizationResult;
use App\Modules\Payments\Data\PaymentOperationRequest;
use App\Modules\Payments\Data\PaymentOperationResult;
use App\Modules\Payments\Data\PaymentTransactionResult;
use App\Modules\Payments\Exceptions\PaymentsDisabled;

final class DisabledPaymentGateway implements PaymentGateway
{
    public function provider(): string
    {
        return 'disabled';
    }

    public function authorize(PaymentAuthorizationRequest $request): PaymentAuthorizationResult
    {
        throw new PaymentsDisabled('Payment authorization is disabled until a provider contract is configured.');
    }

    public function capture(PaymentOperationRequest $request): PaymentOperationResult
    {
        throw new PaymentsDisabled('Payment capture is disabled until a provider contract is configured.');
    }

    public function void(PaymentOperationRequest $request): PaymentOperationResult
    {
        throw new PaymentsDisabled('Payment void is disabled until a provider contract is configured.');
    }

    public function refund(PaymentOperationRequest $request): PaymentOperationResult
    {
        throw new PaymentsDisabled('Payment refund is disabled until a provider contract is configured.');
    }

    public function transaction(string $providerReference): PaymentTransactionResult
    {
        throw new PaymentsDisabled('Payment lookup is disabled until a provider contract is configured.');
    }
}
