<?php

namespace App\Modules\NdiApi;

use App\Modules\NdiApi\Contracts\NdiOrderClient;
use App\Modules\NdiApi\Data\NdiC1OrderLine;
use App\Modules\NdiApi\Data\NdiC1OrderRequest;
use App\Modules\NdiApi\Data\NdiC1OrderResult;
use App\Modules\NdiApi\Enums\NdiAvailabilityStatus;
use App\Modules\NdiApi\Enums\NdiSubmissionStatus;
use App\Modules\NdiApi\Exceptions\NdiApiDisabled;
use App\Modules\NdiApi\Exceptions\NdiAvailabilityNotConfirmed;
use App\Modules\NdiApi\Exceptions\NdiOrderNotReady;
use App\Modules\NdiApi\Models\NdiAvailabilityCheck;
use App\Modules\NdiApi\Models\NdiOrderAttempt;
use App\Modules\Orders\Enums\NdiOrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SubmitNdiOrder
{
    public function __construct(
        private readonly NdiOrderClient $client,
        private readonly OrderLineFingerprint $fingerprint,
    ) {}

    public function execute(Order $order): NdiOrderAttempt
    {
        $attempt = DB::transaction(function () use ($order): NdiOrderAttempt {
            /** @var Order $locked */
            $locked = Order::query()->with('items')->lockForUpdate()->findOrFail($order->id);
            $existing = NdiOrderAttempt::query()->where('order_id', $locked->id)->first();
            if ($existing !== null) {
                return $existing;
            }

            if ($locked->payment_status !== PaymentStatus::Authorized) {
                throw new NdiOrderNotReady('Payment must be authorized, but not captured, before C1.');
            }

            $fingerprint = $this->fingerprint->forOrder($locked);
            $check = NdiAvailabilityCheck::query()
                ->where('order_id', $locked->id)
                ->where('request_fingerprint', $fingerprint)
                ->where('status', NdiAvailabilityStatus::Confirmed)
                ->where('expires_at', '>', now())
                ->latest('responded_at')
                ->first();
            if ($check === null) {
                throw new NdiAvailabilityNotConfirmed('A current action-time Inquiry is required before C1.');
            }

            $locked->update(['ndi_status' => NdiOrderStatus::Submitted]);

            return NdiOrderAttempt::create([
                'order_id' => $locked->id,
                'customer_reference' => $locked->public_reference,
                'request_fingerprint' => $fingerprint,
                'status' => NdiSubmissionStatus::Sending,
                'attempted_at' => now(),
            ]);
        }, 3);

        // Any existing record means an earlier execution crossed the durable
        // no-retry boundary. Return it without making another network request.
        if ($attempt->status !== NdiSubmissionStatus::Sending || ! $attempt->wasRecentlyCreated) {
            return $attempt;
        }

        $order = Order::query()->with('items')->findOrFail($attempt->order_id);
        $request = new NdiC1OrderRequest(
            customerReference: $order->public_reference,
            lines: $order->items->values()->map(fn ($item, int $index): NdiC1OrderLine => new NdiC1OrderLine(
                lineId: (string) ($index + 1),
                buyersArticleId: $item->supplierArticleId(),
                quantity: (int) $item->quantity,
            ))->all(),
            integrationProfile: filled($order->integration_profile) ? (string) $order->integration_profile : NdiIntegrationProfile::LIVE,
        );

        try {
            $result = $this->client->place($request);
        } catch (NdiApiDisabled $exception) {
            DB::transaction(function () use ($attempt): void {
                NdiOrderAttempt::query()->whereKey($attempt->id)->delete();
                Order::query()->whereKey($attempt->order_id)->update(['ndi_status' => NdiOrderStatus::NotSubmitted]);
            }, 3);

            throw $exception;
        } catch (Throwable $exception) {
            $this->markUnknown($attempt);
            throw $exception;
        }

        $outcome = $this->classifyResponse($request, $result);
        $externalReferences = collect($result->lines)
            ->pluck('externalOrderReference')
            ->filter()
            ->unique()
            ->values();
        $externalReference = $externalReferences->count() === 1 ? $externalReferences->first() : null;

        DB::transaction(function () use ($attempt, $order, $result, $outcome, $externalReference): void {
            NdiOrderAttempt::query()->whereKey($attempt->id)->update([
                'status' => $outcome,
                'external_order_reference' => $externalReference,
                'error_code' => $outcome === NdiSubmissionStatus::Unknown ? 'inconsistent_response' : $result->errorCode,
                'response_summary_encrypted' => [
                    'document_id' => $result->documentId,
                    'customer_reference' => $result->customerReference,
                    'lines' => collect($result->lines)->map(fn ($line): array => [
                        'line_id' => $line->lineId,
                        'sku' => $line->buyersArticleId,
                        'error_code' => $line->errorCode,
                        'external_order_reference' => $line->externalOrderReference,
                        'confirmed_quantity' => $line->confirmedQuantity,
                        'delivery_date' => $line->deliveryDate,
                    ])->all(),
                ],
                'responded_at' => now(),
            ]);
            Order::query()->whereKey($order->id)->update([
                'ndi_status' => match ($outcome) {
                    NdiSubmissionStatus::Accepted => NdiOrderStatus::Accepted,
                    NdiSubmissionStatus::Rejected => NdiOrderStatus::Rejected,
                    NdiSubmissionStatus::Unknown, NdiSubmissionStatus::Sending => NdiOrderStatus::Unknown,
                },
            ]);
        }, 3);

        return $attempt->refresh();
    }

    private function markUnknown(NdiOrderAttempt $attempt): void
    {
        DB::transaction(function () use ($attempt): void {
            NdiOrderAttempt::query()->whereKey($attempt->id)->update([
                'status' => NdiSubmissionStatus::Unknown,
                'error_code' => 'transport_or_protocol_unknown',
                'responded_at' => now(),
            ]);
            Order::query()->whereKey($attempt->order_id)->update(['ndi_status' => NdiOrderStatus::Unknown]);
        }, 3);
    }

    private function classifyResponse(NdiC1OrderRequest $request, NdiC1OrderResult $result): NdiSubmissionStatus
    {
        $requested = collect($request->lines)->keyBy(fn (NdiC1OrderLine $line): string => $line->buyersArticleId);
        $returned = collect($result->lines)->keyBy(fn ($line): string => $line->buyersArticleId);
        $externalReferences = collect($result->lines)->pluck('externalOrderReference')->filter()->unique()->values();
        $exactLines = $request->lines !== []
            && $requested->count() === count($request->lines)
            && $requested->count() === $returned->count();

        if ($exactLines) {
            foreach ($requested as $sku => $line) {
                $responseLine = $returned->get($sku);
                if ($responseLine === null
                    || ! $responseLine->isSuccessful()
                    || $responseLine->confirmedQuantity !== $line->quantity) {
                    $exactLines = false;
                    break;
                }
            }
        }

        if ($result->isSuccessful()
            && hash_equals($request->customerReference, $result->customerReference)
            && $exactLines
            && $externalReferences->count() === 1) {
            return NdiSubmissionStatus::Accepted;
        }

        $anyPossibleAcceptance = collect($result->lines)->contains(
            fn ($line): bool => $line->externalOrderReference !== null || $line->confirmedQuantity > 0 || $line->isSuccessful()
        );

        return $anyPossibleAcceptance ? NdiSubmissionStatus::Unknown : NdiSubmissionStatus::Rejected;
    }
}
