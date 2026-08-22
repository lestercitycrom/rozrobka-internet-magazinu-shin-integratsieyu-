<?php

use App\Modules\Catalog\Models\SkuPrice;
use App\Modules\NdiApi\Contracts\NdiInquiryClient;
use App\Modules\NdiApi\Contracts\NdiOrderClient;
use App\Modules\NdiApi\Data\NdiC1OrderRequest;
use App\Modules\NdiApi\Data\NdiC1OrderResult;
use App\Modules\NdiApi\Data\NdiC1OrderResultLine;
use App\Modules\NdiApi\Data\NdiInquiryRequest;
use App\Modules\NdiApi\Data\NdiQuoteLine;
use App\Modules\NdiApi\Data\NdiQuoteResult;
use App\Modules\NdiApi\Exceptions\NdiOrderNotReady;
use App\Modules\Orders\Enums\NdiOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\CaptureAuthorizedPayment;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Exceptions\PaymentOperationRejected;
use App\Modules\Payments\FinalizeOrderPayment;
use App\Modules\Payments\PaymentIdempotencyKey;
use App\Modules\Payments\PrepareOrderPayment;
use Database\Seeders\E2eStorefrontSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePaymentGateway;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(E2eStorefrontSeeder::class);
});

test('coordinator enforces reprice then Inquiry then authorization C1 and capture', function (): void {
    [$gateway, $inquiry, $ndi] = bindPaymentCoordinatorFakes();
    $order = Order::query()->where('public_reference', 'ET-E2E-ORDER')->firstOrFail();

    $authorized = app(PrepareOrderPayment::class)->execute($order, 'https://shop.example.test/payment/return');
    $accepted = app(FinalizeOrderPayment::class)->execute($order->refresh());
    $sameAccepted = app(FinalizeOrderPayment::class)->execute($order->refresh());

    expect($authorized->status)->toBe(PaymentStatus::Authorized)
        ->and($accepted->status)->toBe(PaymentStatus::Authorized)
        ->and($sameAccepted->id)->toBe($accepted->id)
        ->and($order->refresh()->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($order->payment_status)->toBe(PaymentStatus::Authorized)
        ->and($order->ndi_status)->toBe(NdiOrderStatus::Accepted)
        ->and($gateway->captureCalls)->toBeEmpty();

    $order->update(['goods_ready_at' => now(), 'goods_ready_note' => 'Received at EasyTire']);
    $paid = app(CaptureAuthorizedPayment::class)->execute($order->refresh(), PaymentIdempotencyKey::captureFor($order));
    $samePaid = app(CaptureAuthorizedPayment::class)->execute($order->refresh(), PaymentIdempotencyKey::captureFor($order));

    expect($paid->status)->toBe(PaymentStatus::Paid)
        ->and($samePaid->id)->toBe($paid->id)
        ->and($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->ndi_status)->toBe(NdiOrderStatus::Accepted)
        ->and($inquiry->calls)->toBe(1)
        ->and($ndi->calls)->toBe(1)
        ->and($gateway->authorizationCalls)->toHaveCount(1)
        ->and($gateway->captureCalls)->toHaveCount(1)
        ->and($gateway->voidCalls)->toBeEmpty();
});

test('changed current selling price blocks Inquiry and payment authorization', function (): void {
    [$gateway, $inquiry] = bindPaymentCoordinatorFakes();
    $order = Order::query()->where('public_reference', 'ET-E2E-ORDER')->firstOrFail();
    SkuPrice::query()->whereHas('priceList', fn ($query) => $query->where('code', 'public'))->update(['amount' => '1599.0000']);

    expect(fn () => app(PrepareOrderPayment::class)->execute($order, 'https://shop.example.test/payment/return'))
        ->toThrow(PaymentOperationRejected::class, 'selling price')
        ->and($order->refresh()->status)->toBe(OrderStatus::PendingReview)
        ->and($order->payment_status)->toBe(PaymentStatus::NotStarted)
        ->and($inquiry->calls)->toBe(0)
        ->and($gateway->authorizationCalls)->toBeEmpty();
});

test('definitive NDI rejection voids authorization and never captures it', function (): void {
    [$gateway] = bindPaymentCoordinatorFakes(rejectOrder: true);
    $order = Order::query()->where('public_reference', 'ET-E2E-ORDER')->firstOrFail();
    app(PrepareOrderPayment::class)->execute($order, 'https://shop.example.test/payment/return');

    expect(fn () => app(FinalizeOrderPayment::class)->execute($order->refresh()))
        ->toThrow(NdiOrderNotReady::class, 'voided')
        ->and($order->refresh()->ndi_status)->toBe(NdiOrderStatus::Rejected)
        ->and($order->payment_status)->toBe(PaymentStatus::Voided)
        ->and($gateway->captureCalls)->toBeEmpty()
        ->and($gateway->voidCalls)->toHaveCount(1);
});

/** @return array{FakePaymentGateway, object, object} */
function bindPaymentCoordinatorFakes(bool $rejectOrder = false): array
{
    $gateway = new FakePaymentGateway;
    app()->instance(PaymentGateway::class, $gateway);
    $inquiry = new class implements NdiInquiryClient
    {
        public int $calls = 0;

        public function inquire(NdiInquiryRequest $request): NdiQuoteResult
        {
            $this->calls++;
            $line = $request->lines[0];

            return new NdiQuoteResult('A2', '0', [
                new NdiQuoteLine($line->lineId, $line->sku, $line->quantity, $line->quantity, '2030-01-03'),
            ]);
        }
    };
    app()->instance(NdiInquiryClient::class, $inquiry);
    $ndi = new class($rejectOrder) implements NdiOrderClient
    {
        public int $calls = 0;

        public function __construct(private readonly bool $reject) {}

        public function place(NdiC1OrderRequest $request): NdiC1OrderResult
        {
            $this->calls++;
            $line = $request->lines[0];

            return new NdiC1OrderResult('C1', $request->customerReference, $this->reject ? '1' : '0', [
                new NdiC1OrderResultLine(
                    $line->lineId,
                    $line->buyersArticleId,
                    $this->reject ? '1' : '0',
                    $this->reject ? null : 'NDI-E2E-ORDER',
                    $this->reject ? 0 : $line->quantity,
                    $this->reject ? null : '2030-01-03',
                ),
            ]);
        }
    };
    app()->instance(NdiOrderClient::class, $ndi);

    return [$gateway, $inquiry, $ndi];
}
