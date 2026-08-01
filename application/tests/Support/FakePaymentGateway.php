<?php

namespace Tests\Support;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\PaymentAuthorizationRequest;
use App\Modules\Payments\Data\PaymentAuthorizationResult;
use App\Modules\Payments\Data\PaymentOperationRequest;
use App\Modules\Payments\Data\PaymentOperationResult;
use App\Modules\Payments\Exceptions\PaymentProviderRejected;
use RuntimeException;

final class FakePaymentGateway implements PaymentGateway
{
    /** @var list<PaymentAuthorizationRequest> */
    public array $authorizationCalls = [];

    /** @var list<PaymentOperationRequest> */
    public array $captureCalls = [];

    /** @var list<PaymentOperationRequest> */
    public array $voidCalls = [];

    public bool $failNextAuthorizationTransport = false;

    public bool $failNextCaptureTransport = false;

    public bool $failNextVoidTransport = false;

    public bool $rejectNextAuthorization = false;

    /** @var array<string, PaymentAuthorizationResult> */
    private array $authorizations = [];

    public function provider(): string
    {
        return 'fake';
    }

    public function authorize(PaymentAuthorizationRequest $request): PaymentAuthorizationResult
    {
        $this->authorizationCalls[] = $request;
        if ($this->failNextAuthorizationTransport) {
            $this->failNextAuthorizationTransport = false;
            throw new RuntimeException('Synthetic ambiguous authorization transport failure.');
        }
        if ($this->rejectNextAuthorization) {
            $this->rejectNextAuthorization = false;
            throw new PaymentProviderRejected('Synthetic definitive authorization rejection.');
        }

        return $this->authorizations[$request->idempotencyKey] ??= new PaymentAuthorizationResult(
            providerReference: 'fake-'.substr(hash('sha256', $request->idempotencyKey), 0, 24),
            redirectUrl: 'https://payments.example.test/authorize/'.$request->idempotencyKey,
        );
    }

    public function capture(PaymentOperationRequest $request): PaymentOperationResult
    {
        $this->captureCalls[] = $request;
        if ($this->failNextCaptureTransport) {
            $this->failNextCaptureTransport = false;
            throw new RuntimeException('Synthetic ambiguous capture transport failure.');
        }

        return new PaymentOperationResult($request->providerReference);
    }

    public function void(PaymentOperationRequest $request): PaymentOperationResult
    {
        $this->voidCalls[] = $request;
        if ($this->failNextVoidTransport) {
            $this->failNextVoidTransport = false;
            throw new RuntimeException('Synthetic ambiguous void transport failure.');
        }

        return new PaymentOperationResult($request->providerReference);
    }
}
