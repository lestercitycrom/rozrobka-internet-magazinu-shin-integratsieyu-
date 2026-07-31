<?php

namespace App\Modules\NdiApi;

use App\Modules\NdiApi\Contracts\NdiDespatchClient;
use App\Modules\NdiApi\Contracts\NdiOrderStatusClient;
use App\Modules\NdiApi\Data\NdiDespatchRequest;
use App\Modules\NdiApi\Data\NdiOrderStatusRequest;
use App\Modules\NdiApi\Exceptions\NdiOrderReferenceMissing;
use App\Modules\NdiApi\Models\NdiOrderAttempt;
use Illuminate\Support\Facades\DB;

final class RefreshNdiFulfilment
{
    public function __construct(
        private readonly NdiOrderStatusClient $statusClient,
        private readonly NdiDespatchClient $despatchClient,
    ) {}

    public function status(NdiOrderAttempt $attempt): NdiOrderAttempt
    {
        $reference = $this->reference($attempt);
        $result = $this->statusClient->get(new NdiOrderStatusRequest($reference));
        if (! hash_equals($reference, $result->externalOrderReference)) {
            throw new NdiOrderReferenceMissing('NDI status response reference does not match the stored order.');
        }

        return DB::transaction(function () use ($attempt, $result): NdiOrderAttempt {
            /** @var NdiOrderAttempt $locked */
            $locked = NdiOrderAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $locked->update([
                'sales_status' => $result->salesStatus,
                'document_status' => $result->documentStatus,
                'carrier' => $result->carrier,
                'tracking_summary_encrypted' => [
                    'carrier_product' => $result->carrierProduct,
                    'lines' => collect($result->tracking)->map(fn ($line): array => [
                        'number' => $line->trackingNumber,
                        'url' => $line->trackingUrl,
                        'global_status' => $line->globalStatus,
                        'local_status' => $line->localStatus,
                        'occurred_at' => $line->occurredAt,
                    ])->all(),
                ],
                'status_checked_at' => now(),
            ]);

            return $locked->refresh();
        }, 3);
    }

    public function despatch(NdiOrderAttempt $attempt): NdiOrderAttempt
    {
        $reference = $this->reference($attempt);
        $result = $this->despatchClient->get(new NdiDespatchRequest($reference));

        return DB::transaction(function () use ($attempt, $result): NdiOrderAttempt {
            /** @var NdiOrderAttempt $locked */
            $locked = NdiOrderAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $locked->update([
                'tracking_summary_encrypted' => [
                    'error_code' => $result->errorCode,
                    'documents' => collect($result->documents)->map(fn ($document): array => [
                        'document_number' => $document->documentNumber,
                        'arrival_date' => $document->arrivalDate,
                        'lines' => collect($document->tracking)->map(fn ($line): array => [
                            'number' => $line->trackingNumber,
                            'url' => $line->trackingUrl,
                        ])->all(),
                    ])->all(),
                ],
                'despatch_checked_at' => now(),
            ]);

            return $locked->refresh();
        }, 3);
    }

    private function reference(NdiOrderAttempt $attempt): string
    {
        $reference = trim((string) $attempt->external_order_reference);
        if ($reference === '') {
            throw new NdiOrderReferenceMissing('NDI fulfilment reads require an external NDI order reference.');
        }

        return $reference;
    }
}
