<?php

use App\Modules\NdiApi\Contracts\NdiDespatchClient;
use App\Modules\NdiApi\Contracts\NdiOrderStatusClient;
use App\Modules\NdiApi\Data\NdiDespatchDocument;
use App\Modules\NdiApi\Data\NdiDespatchRequest;
use App\Modules\NdiApi\Data\NdiDespatchResult;
use App\Modules\NdiApi\Data\NdiOrderStatusRequest;
use App\Modules\NdiApi\Data\NdiOrderStatusResult;
use App\Modules\NdiApi\Data\NdiTrackingLine;
use App\Modules\NdiApi\Enums\NdiSubmissionStatus;
use App\Modules\NdiApi\Exceptions\NdiOrderReferenceMissing;
use App\Modules\NdiApi\Models\NdiOrderAttempt;
use App\Modules\NdiApi\RefreshNdiFulfilment;
use App\Modules\Orders\Enums\FulfilmentMethod;
use App\Modules\Orders\Enums\NdiOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('status and despatch snapshots are stored without changing local status by guesswork', function (): void {
    $attempt = fulfilmentAttempt('NDI-ORDER-1');
    $statusClient = new class implements NdiOrderStatusClient
    {
        public function get(NdiOrderStatusRequest $request): NdiOrderStatusResult
        {
            return new NdiOrderStatusResult(
                $request->externalOrderReference,
                'Completed',
                'Invoice',
                'Fixture Carrier',
                'Parcel',
                [new NdiTrackingLine('TRACK-1', 'https://tracking.example.test/TRACK-1', 'Internal', 'Label printed', '2030-01-02 10:30:00')],
            );
        }
    };
    $despatchClient = new class implements NdiDespatchClient
    {
        public function get(NdiDespatchRequest $request): NdiDespatchResult
        {
            return new NdiDespatchResult('0', [
                new NdiDespatchDocument('DESADV-1', '2030-01-03', [
                    new NdiTrackingLine('TRACK-1', 'https://tracking.example.test/TRACK-1', null, null, null),
                ]),
            ]);
        }
    };
    app()->instance(NdiOrderStatusClient::class, $statusClient);
    app()->instance(NdiDespatchClient::class, $despatchClient);

    $status = app(RefreshNdiFulfilment::class)->status($attempt);
    $despatch = app(RefreshNdiFulfilment::class)->despatch($status);

    expect($status->sales_status)->toBe('Completed')
        ->and($status->document_status)->toBe('Invoice')
        ->and($status->carrier)->toBe('Fixture Carrier')
        ->and($status->status_checked_at)->not->toBeNull()
        ->and($despatch->tracking_summary_encrypted['documents'][0]['lines'][0]['number'])->toBe('TRACK-1')
        ->and($despatch->despatch_checked_at)->not->toBeNull()
        ->and($despatch->order->status)->toBe(OrderStatus::AwaitingPayment);
});

test('fulfilment reads are impossible without a returned NDI order reference', function (): void {
    $attempt = fulfilmentAttempt(null);
    $statusClient = new class implements NdiOrderStatusClient
    {
        public int $calls = 0;

        public function get(NdiOrderStatusRequest $request): NdiOrderStatusResult
        {
            $this->calls++;
            throw new RuntimeException('Must not be called.');
        }
    };
    app()->instance(NdiOrderStatusClient::class, $statusClient);

    expect(fn () => app(RefreshNdiFulfilment::class)->status($attempt))->toThrow(NdiOrderReferenceMissing::class)
        ->and($statusClient->calls)->toBe(0);
});

function fulfilmentAttempt(?string $externalReference): NdiOrderAttempt
{
    $order = Order::create([
        'public_reference' => 'ET-'.Str::upper(Str::random(14)),
        'idempotency_key' => (string) Str::uuid(),
        'locale' => 'nb',
        'status' => OrderStatus::AwaitingPayment,
        'payment_status' => PaymentStatus::Authorized,
        'ndi_status' => $externalReference === null ? NdiOrderStatus::Unknown : NdiOrderStatus::Accepted,
        'currency' => 'NOK',
        'subtotal_minor' => 100000,
        'total_minor' => 100000,
        'fulfilment_method' => FulfilmentMethod::Pickup,
        'customer_name' => 'Fixture Buyer',
        'customer_email' => 'fixture@example.test',
        'customer_phone' => '+47 900 00 000',
        'terms_accepted_at' => now(),
        'privacy_accepted_at' => now(),
        'pricing_snapshot_at' => now(),
        'submitted_at' => now(),
    ]);

    return NdiOrderAttempt::create([
        'order_id' => $order->id,
        'customer_reference' => $order->public_reference,
        'request_fingerprint' => hash('sha256', 'fixture'),
        'status' => $externalReference === null ? NdiSubmissionStatus::Unknown : NdiSubmissionStatus::Accepted,
        'external_order_reference' => $externalReference,
        'attempted_at' => now(),
        'responded_at' => now(),
    ]);
}
