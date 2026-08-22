<?php

namespace App\Modules\Payments\Dintero;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\PaymentAuthorizationRequest;
use App\Modules\Payments\Data\PaymentAuthorizationResult;
use App\Modules\Payments\Data\PaymentOperationRequest;
use App\Modules\Payments\Data\PaymentOperationResult;
use App\Modules\Payments\Data\PaymentTransactionResult;
use App\Modules\Payments\Enums\ProviderPaymentState;
use App\Modules\Payments\Exceptions\PaymentOperationRejected;
use App\Modules\Payments\Exceptions\PaymentProviderRejected;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

final class DinteroPaymentGateway implements PaymentGateway
{
    public function __construct(private readonly DinteroConfiguration $configuration) {}

    public function provider(): string
    {
        return 'dintero';
    }

    public function authorize(PaymentAuthorizationRequest $request): PaymentAuthorizationResult
    {
        $payload = [
            'url' => array_filter([
                'return_url' => $this->httpsUrl($request->returnUrl, 'return'),
                'callback_url' => $this->httpsUrl($request->callbackUrl, 'callback'),
                'merchant_terms_url' => $request->termsUrl === null ? null : $this->httpsUrl($request->termsUrl, 'terms'),
            ]),
            'customer' => array_filter([
                'customer_id' => $request->orderId,
                'email' => trim($request->customerEmail),
                'phone_number' => trim($request->customerPhone),
            ]),
            'order' => [
                'amount' => $request->amountMinor,
                'currency' => strtoupper($request->currency),
                'merchant_reference' => $request->orderReference,
                'event_reference' => $request->orderReference,
                'items' => $request->items,
            ],
            'configuration' => [
                'auto_capture' => false,
                'channel' => 'ecommerce',
            ],
            'profile_id' => $this->configuration->profileId,
            'metadata' => [
                'ext_order_id' => $request->orderId,
                'ext_idempotency_key' => $request->idempotencyKey,
            ],
        ];

        $response = $this->request()->post($this->paymentsUrl('/sessions-profile'), $payload);
        $this->assertResponse($response, [200, 201], 'create_session');
        $sessionId = $this->reference($response->json('id'));
        $url = $this->httpsUrl((string) $response->json('url'), 'checkout');
        if (! str_starts_with($sessionId, $this->configuration->accountId.'.')) {
            throw new PaymentOperationRejected('Dintero returned a session from another account.');
        }

        return new PaymentAuthorizationResult(
            providerReference: $sessionId,
            redirectUrl: $url,
            state: ProviderPaymentState::Pending,
            providerSessionReference: $sessionId,
        );
    }

    public function capture(PaymentOperationRequest $request): PaymentOperationResult
    {
        $response = $this->request()->post($this->paymentsUrl('/transactions/'.rawurlencode($request->providerReference).'/capture'), [
            'amount' => $request->amountMinor,
            'capture_reference' => $request->operationReference,
            'items' => $request->items,
            'final_capture' => true,
            'metadata' => ['ext_idempotency_key' => $request->idempotencyKey],
        ]);
        $this->assertResponse($response, [200, 202], 'capture');
        $result = $this->transactionFromPayload($response->json());
        if ($result->state !== ProviderPaymentState::Paid) {
            throw new \RuntimeException('Dintero capture was accepted but is not final yet; reconciliation is required.');
        }

        return new PaymentOperationResult($result->providerReference);
    }

    public function void(PaymentOperationRequest $request): PaymentOperationResult
    {
        $response = $this->request()->post($this->paymentsUrl('/transactions/'.rawurlencode($request->providerReference).'/void'));
        $this->assertResponse($response, [200, 202], 'void');
        $result = $this->transactionFromPayload($response->json());
        if ($result->state !== ProviderPaymentState::Voided) {
            throw new \RuntimeException('Dintero void was accepted but is not final yet; reconciliation is required.');
        }

        return new PaymentOperationResult($result->providerReference);
    }

    public function transaction(string $providerReference): PaymentTransactionResult
    {
        $reference = $this->reference($providerReference);
        $response = $this->request()->get($this->paymentsUrl('/transactions/'.rawurlencode($reference)));
        $this->assertResponse($response, [200], 'transaction_lookup');

        return $this->transactionFromPayload($response->json());
    }

    private function transactionFromPayload(mixed $payload): PaymentTransactionResult
    {
        if (! is_array($payload)) {
            throw new PaymentOperationRejected('Dintero returned an invalid transaction payload.');
        }

        $externalStatus = strtoupper(trim((string) ($payload['status'] ?? '')));
        $state = match ($externalStatus) {
            'AUTHORIZED' => ProviderPaymentState::Authorized,
            'CAPTURED' => ProviderPaymentState::Paid,
            'AUTHORIZATION_VOIDED' => ProviderPaymentState::Voided,
            'FAILED', 'DECLINED' => ProviderPaymentState::Failed,
            'INITIATED', 'ON_HOLD', 'UNKNOWN', 'PARTIALLY_CAPTURED', 'PARTIALLY_CAPTURED_REFUNDED', 'PARTIALLY_REFUNDED', 'REFUNDED' => ProviderPaymentState::Pending,
            default => throw new PaymentOperationRejected('Dintero returned an unsupported transaction status.'),
        };

        return new PaymentTransactionResult(
            providerReference: $this->reference($payload['id'] ?? null),
            providerSessionReference: $this->reference($payload['session_id'] ?? null),
            merchantReference: trim((string) ($payload['merchant_reference'] ?? '')),
            state: $state,
            amountMinor: (int) ($payload['amount'] ?? -1),
            currency: strtoupper(trim((string) ($payload['currency'] ?? ''))),
            externalStatus: $externalStatus,
            paymentProductType: isset($payload['payment_product_type']) ? (string) $payload['payment_product_type'] : null,
            updatedAt: isset($payload['updated_at']) ? (string) $payload['updated_at'] : null,
        );
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->connectTimeout($this->configuration->connectTimeout)
            ->timeout($this->configuration->timeout)
            ->withToken($this->accessToken())
            ->withHeaders([
                'Dintero-System-Name' => 'EasyTire',
                'Dintero-System-Version' => '1.0',
            ]);
    }

    private function accessToken(): string
    {
        $cacheKey = 'dintero:oauth:'.hash('sha256', $this->configuration->accountId.'|'.$this->configuration->clientId);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            try {
                return Crypt::decryptString($cached);
            } catch (Throwable) {
                Cache::forget($cacheKey);
            }
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout($this->configuration->connectTimeout)
                ->timeout($this->configuration->timeout)
                ->withBasicAuth($this->configuration->clientId, $this->configuration->clientSecret)
                ->post($this->configuration->accountBaseUrl().'/auth/token', [
                    'grant_type' => 'client_credentials',
                    'audience' => $this->configuration->accountBaseUrl(),
                ]);
        } catch (ConnectionException $exception) {
            throw new \RuntimeException('Dintero authentication transport failed.', previous: $exception);
        }

        $this->assertResponse($response, [200], 'authentication');
        $token = trim((string) $response->json('access_token'));
        $expiresIn = max(120, (int) $response->json('expires_in', 3600));
        if ($token === '') {
            throw new PaymentOperationRejected('Dintero authentication returned no access token.');
        }
        Cache::put($cacheKey, Crypt::encryptString($token), now()->addSeconds(max(60, $expiresIn - 120)));

        return $token;
    }

    private function assertResponse(Response $response, array $accepted, string $operation): void
    {
        if (in_array($response->status(), $accepted, true)) {
            return;
        }

        if (in_array($response->status(), [400, 402, 404, 409, 422], true)) {
            throw new PaymentProviderRejected("Dintero rejected {$operation} (HTTP {$response->status()}).");
        }

        throw new \RuntimeException("Dintero {$operation} failed ambiguously (HTTP {$response->status()}).");
    }

    private function paymentsUrl(string $path): string
    {
        return $this->configuration->accountBaseUrl().'/payments'.$path;
    }

    private function reference(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '' || mb_strlen($value) > 255) {
            throw new PaymentOperationRejected('Dintero returned an invalid reference.');
        }

        return $value;
    }

    private function httpsUrl(string $value, string $purpose): string
    {
        $value = trim($value);
        if (filter_var($value, FILTER_VALIDATE_URL) === false || parse_url($value, PHP_URL_SCHEME) !== 'https') {
            throw new PaymentOperationRejected("Dintero {$purpose} URL must be a valid HTTPS URL.");
        }

        return $value;
    }
}
