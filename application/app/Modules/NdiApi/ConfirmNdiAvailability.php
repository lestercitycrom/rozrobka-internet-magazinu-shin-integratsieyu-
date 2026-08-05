<?php

namespace App\Modules\NdiApi;

use App\Modules\NdiApi\Contracts\NdiInquiryClient;
use App\Modules\NdiApi\Data\NdiInquiryLine;
use App\Modules\NdiApi\Data\NdiInquiryRequest;
use App\Modules\NdiApi\Enums\NdiAvailabilityStatus;
use App\Modules\NdiApi\Models\NdiAvailabilityCheck;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Str;
use Throwable;

final class ConfirmNdiAvailability
{
    public function __construct(
        private readonly NdiInquiryClient $client,
        private readonly OrderLineFingerprint $fingerprint,
    ) {}

    public function execute(Order $order, string $sourceAction): NdiAvailabilityCheck
    {
        if (! in_array($sourceAction, ['cart_add', 'checkout'], true)) {
            throw new \InvalidArgumentException('NDI Inquiry requires an explicit buyer action.');
        }

        $order->loadMissing('items');
        if ($order->items->isEmpty()) {
            throw new \InvalidArgumentException('NDI Inquiry requires at least one order item.');
        }

        $fingerprint = $this->fingerprint->forOrder($order);
        $existing = NdiAvailabilityCheck::query()
            ->where('order_id', $order->id)
            ->where('request_fingerprint', $fingerprint)
            ->where('status', NdiAvailabilityStatus::Confirmed)
            ->where('expires_at', '>', now())
            ->latest('responded_at')
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $documentId = 'INQ-'.Str::ulid();
        $check = NdiAvailabilityCheck::create([
            'order_id' => $order->id,
            'document_id' => $documentId,
            'source_action' => $sourceAction,
            'request_fingerprint' => $fingerprint,
            'status' => NdiAvailabilityStatus::Pending,
            'requested_at' => now(),
        ]);

        $requested = $order->items->values()->map(fn ($item, int $index): NdiInquiryLine => new NdiInquiryLine(
            lineId: (string) ($index + 1),
            sku: (string) $item->external_sku,
            quantity: (int) $item->quantity,
        ));

        try {
            $quote = $this->client->inquire(new NdiInquiryRequest(
                documentId: 'A2',
                customerReference: $order->public_reference,
                lines: $requested->all(),
            ));
        } catch (Throwable $exception) {
            $check->update([
                'status' => NdiAvailabilityStatus::Failed,
                'error_code' => 'transport_or_protocol_failure',
                'responded_at' => now(),
            ]);

            throw $exception;
        }

        $availableBySku = collect($quote->lines)->keyBy(fn ($line): string => $line->sku);
        $available = $quote->isSuccessful() && $requested->every(function (NdiInquiryLine $line) use ($availableBySku): bool {
            $quoted = $availableBySku->get($line->sku);

            return $quoted !== null && $quoted->availableQuantity >= $line->quantity;
        });
        $ttl = max(30, (int) config('ndi_api.inquiry_ttl_seconds', 300));

        $check->update([
            'status' => $available ? NdiAvailabilityStatus::Confirmed : NdiAvailabilityStatus::Unavailable,
            'error_code' => $quote->errorCode === '0' ? null : $quote->errorCode,
            'response_summary_encrypted' => [
                'document_id' => $quote->documentId,
                'lines' => collect($quote->lines)->map(fn ($line): array => [
                    'sku' => $line->sku,
                    'requested_quantity' => $line->requestedQuantity,
                    'available_quantity' => $line->availableQuantity,
                    'delivery_date' => $line->deliveryDate,
                ])->all(),
            ],
            'responded_at' => now(),
            'expires_at' => now()->addSeconds($ttl),
        ]);

        return $check->refresh();
    }
}
