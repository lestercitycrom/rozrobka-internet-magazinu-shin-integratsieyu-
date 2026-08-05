<?php

namespace App\Console\Commands;

use App\Modules\NdiApi\Contracts\NdiInquiryClient;
use App\Modules\NdiApi\Data\NdiInquiryLine;
use App\Modules\NdiApi\Data\NdiInquiryRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

final class NdiInquirySmoke extends Command
{
    protected $signature = 'ndi:inquiry:smoke
        {sku : Explicit NDI test article identifier}
        {--quantity=1 : Positive requested quantity}
        {--reference= : Optional non-sensitive test reference}';

    protected $description = 'Run one explicit read-only NDI Inquiry outside automated tests.';

    public function handle(NdiInquiryClient $client): int
    {
        if (app()->environment('production')) {
            $this->components->error('Live NDI smoke checks are forbidden in the production app environment.');

            return self::FAILURE;
        }

        $sku = trim((string) $this->argument('sku'));
        $quantity = filter_var($this->option('quantity'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($sku === '' || mb_strlen($sku) > 100 || $quantity === false) {
            $this->components->error('Provide a valid SKU and a positive integer quantity.');

            return self::INVALID;
        }

        $reference = trim((string) $this->option('reference'));
        if ($reference === '') {
            $reference = 'EASYTIRE-INQUIRY-'.Str::upper(Str::random(10));
        }

        try {
            $result = $client->inquire(new NdiInquiryRequest(
                documentId: 'A2',
                customerReference: $reference,
                lines: [new NdiInquiryLine('1', $sku, (int) $quantity)],
            ));
        } catch (Throwable) {
            $this->components->error('NDI Inquiry failed. See normalized application logs; no raw response was printed.');

            return self::FAILURE;
        }

        $line = collect($result->lines)->firstWhere('sku', $sku);
        $this->table(
            ['Error', 'SKU', 'Requested', 'Available', 'Delivery date'],
            [[
                $result->errorCode,
                $sku,
                (int) $quantity,
                $line?->availableQuantity ?? 0,
                $line?->deliveryDate ?? 'not supplied',
            ]],
        );

        return $result->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
