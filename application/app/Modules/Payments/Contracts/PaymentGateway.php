<?php

namespace App\Modules\Payments\Contracts;

use App\Modules\Payments\Data\PaymentAuthorizationRequest;
use App\Modules\Payments\Data\PaymentAuthorizationResult;
use App\Modules\Payments\Data\PaymentOperationRequest;
use App\Modules\Payments\Data\PaymentOperationResult;

interface PaymentGateway
{
    public function provider(): string;

    /** The adapter must make retries with the same idempotency key return the original authorization. */
    public function authorize(PaymentAuthorizationRequest $request): PaymentAuthorizationResult;

    /** The adapter must not capture twice when the same idempotency key is retried. */
    public function capture(PaymentOperationRequest $request): PaymentOperationResult;

    /** The adapter must not void twice when the same idempotency key is retried. */
    public function void(PaymentOperationRequest $request): PaymentOperationResult;
}
