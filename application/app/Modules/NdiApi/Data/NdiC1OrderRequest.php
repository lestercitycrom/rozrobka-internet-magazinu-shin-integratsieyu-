<?php

namespace App\Modules\NdiApi\Data;

use InvalidArgumentException;

final readonly class NdiC1OrderRequest
{
    /** @param list<NdiC1OrderLine> $lines */
    public function __construct(
        public string $customerReference,
        public array $lines,
        public string $transportPriority = 'REPL',
        public ?string $deliveryRemarks = null,
    ) {
        if ($this->customerReference === '' || mb_strlen($this->customerReference) > 50) {
            throw new InvalidArgumentException('NDI C1 customer reference must contain 1 to 50 characters.');
        }

        if ($this->transportPriority === '' || mb_strlen($this->transportPriority) > 20) {
            throw new InvalidArgumentException('NDI C1 transport priority must contain 1 to 20 characters.');
        }

        if ($this->deliveryRemarks !== null && mb_strlen($this->deliveryRemarks) > 100) {
            throw new InvalidArgumentException('NDI C1 delivery remarks may contain at most 100 characters.');
        }

        if ($this->lines === []) {
            throw new InvalidArgumentException('NDI C1 order must contain at least one line.');
        }

        foreach ($this->lines as $line) {
            if (! $line instanceof NdiC1OrderLine) {
                throw new InvalidArgumentException('NDI C1 lines must be NdiC1OrderLine values.');
            }
        }

        $lineIds = array_map(fn (NdiC1OrderLine $line): string => $line->lineId, $this->lines);
        if (count($lineIds) !== count(array_unique($lineIds))) {
            throw new InvalidArgumentException('NDI C1 line IDs must be unique.');
        }
    }
}
