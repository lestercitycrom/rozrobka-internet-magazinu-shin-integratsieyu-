<?php

use App\Modules\Payments\Data\PaymentAuthorizationRequest;
use App\Modules\Payments\Data\PaymentOperationRequest;
use App\Modules\Payments\Dintero\DinteroCallbackSignature;
use App\Modules\Payments\Dintero\DinteroConfiguration;
use App\Modules\Payments\Dintero\DinteroPaymentGateway;
use App\Modules\Payments\Enums\ProviderPaymentState;
use App\Modules\Payments\Exceptions\PaymentOperationRejected;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::flush();
    config()->set([
        'payments.dintero.environment' => 'test',
        'payments.dintero.test_account_id' => 'T12345678',
        'payments.dintero.production_account_id' => 'P12345678',
        'payments.dintero.client_id' => 'test-client',
        'payments.dintero.client_secret' => 'test-secret',
        'payments.dintero.require_callback_signature' => false,
        'payments.dintero.profile_id' => 'default',
        'payments.dintero.api_base_url' => 'https://api.dintero.com/v1',
        'payments.dintero.connect_timeout' => 1,
        'payments.dintero.timeout' => 2,
    ]);
});

test('Dintero cannot be enabled without callback signature protection', function (): void {
    config()->set([
        'payments.dintero.require_callback_signature' => true,
        'payments.dintero.callback_secret' => null,
    ]);

    expect(fn () => new DinteroConfiguration)->toThrow(PaymentOperationRejected::class);
});

test('Dintero test checkout creates a manual-capture session with an exact immutable order', function (): void {
    Http::fake([
        'https://api.dintero.com/v1/accounts/T12345678/auth/token' => Http::response([
            'access_token' => 'sandbox-token',
            'expires_in' => 3600,
        ]),
        'https://api.dintero.com/v1/accounts/T12345678/payments/sessions-profile' => Http::response([
            'id' => 'T12345678.session-1',
            'url' => 'https://checkout.dintero.com/T12345678.session-1',
        ], 201),
    ]);

    $gateway = new DinteroPaymentGateway(new DinteroConfiguration);
    $result = $gateway->authorize(new PaymentAuthorizationRequest(
        orderId: '01ORDERULID',
        orderReference: 'ET-TEST-1001',
        amountMinor: 149900,
        currency: 'NOK',
        idempotencyKey: '11111111-1111-4111-8111-111111111111',
        returnUrl: 'https://staging.example.test/betaling/dintero/retur/ET-TEST-1001',
        callbackUrl: 'https://staging.example.test/betaling/dintero/callback',
        termsUrl: 'https://staging.example.test/kjopsvilkar',
        customerName: 'Test Customer',
        customerEmail: 'test@example.test',
        customerPhone: '+4790000000',
        items: [[
            'id' => 'SKU-1',
            'line_id' => 'product-1',
            'description' => 'Testdekk',
            'quantity' => 1,
            'amount' => 149900,
            'vat_amount' => 29980,
            'vat' => 25,
        ]],
    ));

    expect($result->state)->toBe(ProviderPaymentState::Pending)
        ->and($result->providerSessionReference)->toBe('T12345678.session-1')
        ->and($result->redirectUrl)->toBe('https://checkout.dintero.com/T12345678.session-1');

    Http::assertSent(function (HttpRequest $request): bool {
        if (! str_ends_with($request->url(), '/payments/sessions-profile')) {
            return true;
        }

        return $request['profile_id'] === 'default'
            && $request['configuration']['auto_capture'] === false
            && $request['configuration']['channel'] === 'ecommerce'
            && $request['order']['merchant_reference'] === 'ET-TEST-1001'
            && $request['order']['amount'] === 149900
            && $request['order']['items'][0]['amount'] === 149900
            && $request['url']['callback_url'] === 'https://staging.example.test/betaling/dintero/callback';
    });
});

test('Dintero transaction lookup and final capture use the authoritative transaction and all items', function (): void {
    Http::fake(function (HttpRequest $request) {
        if (str_ends_with($request->url(), '/auth/token')) {
            return Http::response(['access_token' => 'sandbox-token', 'expires_in' => 3600]);
        }
        if ($request->method() === 'GET') {
            return Http::response(dinteroTransaction('AUTHORIZED'));
        }

        return Http::response(dinteroTransaction('CAPTURED'));
    });

    $gateway = new DinteroPaymentGateway(new DinteroConfiguration);
    $transaction = $gateway->transaction('txn-1');
    $capture = $gateway->capture(new PaymentOperationRequest(
        providerReference: 'txn-1',
        amountMinor: 149900,
        currency: 'NOK',
        idempotencyKey: '22222222-2222-4222-8222-222222222222',
        operationReference: 'ET-TEST-1001',
        items: [['line_id' => 'product-1', 'quantity' => 1, 'amount' => 149900]],
    ));

    expect($transaction->state)->toBe(ProviderPaymentState::Authorized)
        ->and($transaction->merchantReference)->toBe('ET-TEST-1001')
        ->and($capture->providerReference)->toBe('txn-1');

    Http::assertSent(fn (HttpRequest $request): bool => ! str_ends_with($request->url(), '/capture') || (
        $request['amount'] === 149900
        && $request['capture_reference'] === 'ET-TEST-1001'
        && $request['final_capture'] === true
        && $request['items'][0]['line_id'] === 'product-1'
    ));
});

test('callback signature rejects tampering and expired callbacks', function (): void {
    config()->set([
        'payments.dintero.callback_secret' => 'callback-secret',
        'payments.dintero.require_callback_signature' => true,
    ]);
    $timestamp = time();
    $query = [
        'merchant_reference' => 'ET-TEST-1001',
        'session_id' => 'T12345678.session-1',
        'time' => '2026-08-22T10:00:00Z',
        'transaction_id' => 'txn-1',
    ];
    ksort($query, SORT_STRING);
    $path = '/betaling/dintero/callback';
    $payload = implode("\n", [
        (string) $timestamp,
        'T12345678',
        'GET',
        'staging.example.test',
        $path,
        http_build_query($query, '', '&', PHP_QUERY_RFC1738),
    ]);
    $signature = hash_hmac('sha256', $payload, 'callback-secret');
    $request = Request::create('https://staging.example.test'.$path.'?'.http_build_query($query), 'GET');
    $request->headers->set('Dintero-Signature', "t={$timestamp},v0-hmac-sha256={$signature}");

    expect(app(DinteroCallbackSignature::class)->isValid($request))->toBeTrue();

    $request->query->set('transaction_id', 'tampered');
    expect(app(DinteroCallbackSignature::class)->isValid($request))->toBeFalse();

    $expired = Request::create('https://staging.example.test'.$path.'?'.http_build_query($query), 'GET');
    $expired->headers->set('Dintero-Signature', 't='.($timestamp - 301).',v0-hmac-sha256='.$signature);
    expect(app(DinteroCallbackSignature::class)->isValid($expired))->toBeFalse();
});

function dinteroTransaction(string $status): array
{
    return [
        'id' => 'txn-1',
        'session_id' => 'T12345678.session-1',
        'merchant_reference' => 'ET-TEST-1001',
        'status' => $status,
        'amount' => 149900,
        'currency' => 'NOK',
        'payment_product_type' => 'card',
        'updated_at' => '2026-08-22T10:00:00Z',
    ];
}
