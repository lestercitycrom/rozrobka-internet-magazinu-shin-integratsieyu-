<?php

namespace Tests\Support;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\PaymentAuthorizationRequest;
use App\Modules\Payments\Data\PaymentAuthorizationResult;
use App\Modules\Payments\Data\PaymentOperationRequest;
use App\Modules\Payments\Data\PaymentOperationResult;
use App\Modules\Payments\Data\PaymentTransactionResult;
use App\Modules\Payments\Enums\ProviderPaymentState;
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

    /** @var list<PaymentOperationRequest> */
    public array $refundCalls = [];

    public bool $failNextAuthorizationTransport = false;

    public bool $failNextCaptureTransport = false;

    public bool $failNextVoidTransport = false;

    public bool $failNextRefundTransport = false;

    public bool $rejectNextAuthorization = false;

    /** @var array<string, PaymentAuthorizationResult> */
    private array $authorizations = [];

    /** @var array<string, PaymentTransactionResult> */
    public array $transactions = [];

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

        $result = $this->authorizations[$request->idempotencyKey] ??= new PaymentAuthorizationResult(
            providerReference: 'fake-'.substr(hash('sha256', $request->idempotencyKey), 0, 24),
            redirectUrl: 'https://payments.example.test/authorize/'.$request->idempotencyKey,
        );
        $this->transactions[$result->providerReference] = new PaymentTransactionResult(
            providerReference: $result->providerReference,
            providerSessionReference: $result->providerSessionReference ?? $result->providerReference,
            merchantReference: $request->orderReference,
            state: ProviderPaymentState::Authorized,
            amountMinor: $request->amountMinor,
            currency: $request->currency,
            externalStatus: 'AUTHORIZED',
            paymentProductType: 'dintero_psp.creditcard',
            authorizationExpiresAt: now()->addDays(7)->toIso8601String(),
            cardLastFour: '4242',
        );

        return $result;
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

    public function refund(PaymentOperationRequest $request): PaymentOperationResult
    {
        $this->refundCalls[] = $request;
        if ($this->failNextRefundTransport) {
            $this->failNextRefundTransport = false;
            throw new RuntimeException('Synthetic ambiguous refund transport failure.');
        }

        return new PaymentOperationResult($request->providerReference);
    }

    public function transaction(string $providerReference): PaymentTransactionResult
    {
        return $this->transactions[$providerReference] ?? throw new RuntimeException('Synthetic transaction was not found.');
    }
}
