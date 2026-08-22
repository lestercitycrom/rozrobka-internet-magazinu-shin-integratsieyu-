<?php

namespace App\Modules\NdiApi\Data;

use App\Modules\NdiApi\NdiIntegrationProfile;
use InvalidArgumentException;

final readonly class NdiInquiryRequest
{
    /** @param list<NdiInquiryLine> $lines */
    public function __construct(
        public string $documentId,
        public string $customerReference,
        public array $lines,
        public string $variant = '5',
        public string $integrationProfile = NdiIntegrationProfile::LIVE,
    ) {
        if ($this->documentId !== 'A2') {
            throw new InvalidArgumentException('NDI A2 inquiry document ID must be A2.');
        }

        if ($this->customerReference === '' || mb_strlen($this->customerReference) > 50) {
            throw new InvalidArgumentException('NDI customer reference must contain 1 to 50 characters.');
        }

        if ($this->lines === []) {
            throw new InvalidArgumentException('NDI inquiry must contain at least one line.');
        }

        if ($this->variant === '' || mb_strlen($this->variant) > 10) {
            throw new InvalidArgumentException('NDI inquiry variant must contain 1 to 10 characters.');
        }

        if (! NdiIntegrationProfile::isKnown($this->integrationProfile)) {
            throw new InvalidArgumentException('Unknown NDI inquiry integration profile.');
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
