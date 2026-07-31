<?php

use App\Modules\NdiApi\ConfirmNdiAvailability;
use App\Modules\NdiApi\Contracts\NdiInquiryClient;
use App\Modules\NdiApi\Contracts\NdiOrderClient;
use App\Modules\NdiApi\Data\NdiC1OrderRequest;
use App\Modules\NdiApi\Data\NdiC1OrderResult;
use App\Modules\NdiApi\Data\NdiC1OrderResultLine;
use App\Modules\NdiApi\Data\NdiInquiryRequest;
use App\Modules\NdiApi\Data\NdiQuoteLine;
use App\Modules\NdiApi\Data\NdiQuoteResult;
use App\Modules\NdiApi\Enums\NdiAvailabilityStatus;
use App\Modules\NdiApi\Enums\NdiSubmissionStatus;
use App\Modules\NdiApi\Exceptions\NdiAvailabilityNotConfirmed;
use App\Modules\NdiApi\Exceptions\NdiOrderNotReady;
use App\Modules\NdiApi\Exceptions\NdiTransportException;
use App\Modules\NdiApi\Models\NdiAvailabilityCheck;
use App\Modules\NdiApi\Models\NdiOrderAttempt;
use App\Modules\NdiApi\OrderLineFingerprint;
use App\Modules\NdiApi\SubmitNdiOrder;
use App\Modules\Orders\Enums\FulfilmentMethod;
use App\Modules\Orders\Enums\NdiOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('action-time Inquiry records and reuses a current exact availability confirmation', function (): void {
    $order = ndiLifecycleOrder(PaymentStatus::NotStarted);
    $client = new class implements NdiInquiryClient
    {
        public int $calls = 0;

        public function inquire(NdiInquiryRequest $request): NdiQuoteResult
        {
            $this->calls++;

            return new NdiQuoteResult('QUOTE-1', '0', [new NdiQuoteLine('1', 'TEST-SKU', 2, 4, '2030-01-03')]);
        }
    };
    app()->instance(NdiInquiryClient::class, $client);

    $first = app(ConfirmNdiAvailability::class)->execute($order, 'checkout');
    $second = app(ConfirmNdiAvailability::class)->execute($order, 'checkout');

    expect($first->status)->toBe(NdiAvailabilityStatus::Confirmed)
        ->and($second->id)->toBe($first->id)
        ->and($client->calls)->toBe(1)
        ->and($first->response_summary_encrypted['lines'][0]['available_quantity'])->toBe(4);
});

test('C1 requires both authorized payment and a current matching Inquiry', function (): void {
    $notAuthorized = ndiLifecycleOrder(PaymentStatus::NotStarted);
    app()->instance(NdiOrderClient::class, acceptingNdiOrderClient());

    expect(fn () => app(SubmitNdiOrder::class)->execute($notAuthorized))->toThrow(NdiOrderNotReady::class);

    $authorized = ndiLifecycleOrder(PaymentStatus::Authorized);
    expect(fn () => app(SubmitNdiOrder::class)->execute($authorized))->toThrow(NdiAvailabilityNotConfirmed::class)
        ->and(NdiOrderAttempt::query()->count())->toBe(0);
});

test('accepted C1 is durable and a duplicate call never sends a second order', function (): void {
    $order = ndiLifecycleOrder(PaymentStatus::Authorized);
    confirmLifecycleAvailability($order);
    $client = acceptingNdiOrderClient();
    app()->instance(NdiOrderClient::class, $client);

    $first = app(SubmitNdiOrder::class)->execute($order);
    $second = app(SubmitNdiOrder::class)->execute($order);

    expect($first->status)->toBe(NdiSubmissionStatus::Accepted)
        ->and($first->external_order_reference)->toBe('NDI-ORDER-1')
        ->and($second->id)->toBe($first->id)
        ->and($client->calls)->toBe(1)
        ->and($order->refresh()->ndi_status)->toBe(NdiOrderStatus::Accepted);
});

test('an uncertain C1 outcome is stored and can never be retried automatically', function (): void {
    $order = ndiLifecycleOrder(PaymentStatus::Authorized);
    confirmLifecycleAvailability($order);
    $client = new class implements NdiOrderClient
    {
        public int $calls = 0;

        public function place(NdiC1OrderRequest $request): NdiC1OrderResult
        {
            $this->calls++;

            throw new NdiTransportException('Fixture timeout after dispatch.');
        }
    };
    app()->instance(NdiOrderClient::class, $client);

    expect(fn () => app(SubmitNdiOrder::class)->execute($order))->toThrow(NdiTransportException::class);

    $existing = app(SubmitNdiOrder::class)->execute($order);
    expect($existing->status)->toBe(NdiSubmissionStatus::Unknown)
        ->and($client->calls)->toBe(1)
        ->and($order->refresh()->ndi_status)->toBe(NdiOrderStatus::Unknown);
});

test('a partial or inconsistent C1 response goes to manual review rather than acceptance', function (): void {
    $order = ndiLifecycleOrder(PaymentStatus::Authorized);
    confirmLifecycleAvailability($order);
    $client = new class implements NdiOrderClient
    {
        public function place(NdiC1OrderRequest $request): NdiC1OrderResult
        {
            return new NdiC1OrderResult('C1', $request->customerReference, '0', [
                new NdiC1OrderResultLine('1', 'TEST-SKU', '0', 'NDI-ORDER-1', 1, '2030-01-03'),
            ]);
        }
    };
    app()->instance(NdiOrderClient::class, $client);

    $attempt = app(SubmitNdiOrder::class)->execute($order);

    expect($attempt->status)->toBe(NdiSubmissionStatus::Unknown)
        ->and($attempt->error_code)->toBe('inconsistent_response')
        ->and($order->refresh()->ndi_status)->toBe(NdiOrderStatus::Unknown);
});

function ndiLifecycleOrder(PaymentStatus $paymentStatus): Order
{
    $order = Order::create([
        'public_reference' => 'ET-'.Str::upper(Str::random(14)),
        'idempotency_key' => (string) Str::uuid(),
        'locale' => 'nb',
        'status' => OrderStatus::AwaitingPayment,
        'payment_status' => $paymentStatus,
        'ndi_status' => NdiOrderStatus::NotSubmitted,
        'currency' => 'NOK',
        'subtotal_minor' => 200000,
        'total_minor' => 200000,
        'fulfilment_method' => FulfilmentMethod::Pickup,
        'customer_name' => 'Fixture Buyer',
        'customer_email' => 'fixture@example.test',
        'customer_phone' => '+47 900 00 000',
        'terms_accepted_at' => now(),
        'privacy_accepted_at' => now(),
        'pricing_snapshot_at' => now(),
        'submitted_at' => now(),
    ]);
    $order->items()->create([
        'external_sku' => 'TEST-SKU',
        'product_title' => 'Fixture Tire',
        'quantity' => 2,
        'unit_price_minor' => 100000,
        'line_total_minor' => 200000,
        'currency' => 'NOK',
        'product_snapshot' => ['product_type' => 'tire'],
        'pricing_snapshot' => ['rule_id' => 1],
    ]);

    return $order->load('items');
}

function confirmLifecycleAvailability(Order $order): NdiAvailabilityCheck
{
    return NdiAvailabilityCheck::create([
        'order_id' => $order->id,
        'document_id' => 'INQ-'.Str::ulid(),
        'source_action' => 'checkout',
        'request_fingerprint' => app(OrderLineFingerprint::class)->forOrder($order),
        'status' => NdiAvailabilityStatus::Confirmed,
        'response_summary_encrypted' => ['fixture' => true],
        'requested_at' => now(),
        'responded_at' => now(),
        'expires_at' => now()->addMinutes(5),
    ]);
}

function acceptingNdiOrderClient(): object
{
    return new class implements NdiOrderClient
    {
        public int $calls = 0;

        public function place(NdiC1OrderRequest $request): NdiC1OrderResult
        {
            $this->calls++;

            return new NdiC1OrderResult('C1', $request->customerReference, '0', [
                new NdiC1OrderResultLine('1', 'TEST-SKU', '0', 'NDI-ORDER-1', 2, '2030-01-03'),
            ]);
        }
    };
}
