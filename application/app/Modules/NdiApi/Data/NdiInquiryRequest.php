<?php

namespace App\Modules\NdiApi\Data;

use InvalidArgumentException;

final readonly class NdiInquiryRequest
{
    /** @param list<NdiInquiryLine> $lines */
    public function __construct(
        public string $documentId,
        public string $customerReference,
        public array $lines,
        public string $variant = '',
    ) {
        if ($this->documentId === '' || mb_strlen($this->documentId) > 50) {
            throw new InvalidArgumentException('NDI document ID must contain 1 to 50 characters.');
        }

        if ($this->customerReference === '' || mb_strlen($this->customerReference) > 50) {
            throw new InvalidArgumentException('NDI customer reference must contain 1 to 50 characters.');
        }

        if ($this->lines === []) {
            throw new InvalidArgumentException('NDI inquiry must contain at least one line.');
        }

        foreach ($this->lines as $line) {
            if (! $line instanceof NdiInquiryLine) {
                throw new InvalidArgumentException('NDI inquiry lines must be NdiInquiryLine values.');
            }
        }

        $lineIds = array_map(fn (NdiInquiryLine $line): string => $line->lineId, $this->lines);
        if (count($lineIds) !== count(array_unique($lineIds))) {
            throw new InvalidArgumentException('NDI line IDs must be unique within an inquiry.');
        }
    }
}
