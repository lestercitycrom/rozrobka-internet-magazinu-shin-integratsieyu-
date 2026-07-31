<?php

namespace App\Modules\NdiApi\Data;

final readonly class NdiC1OrderResult
{
    /** @param list<NdiC1OrderResultLine> $lines */
    public function __construct(
        public string $documentId,
        public string $customerReference,
        public string $errorCode,
        public array $lines,
    ) {}

    public function isSuccessful(): bool
    {
        if ($this->errorCode !== '0' || $this->lines === []) {
            return false;
        }

        foreach ($this->lines as $line) {
            if (! $line->isSuccessful()) {
                return false;
            }
        }

        return true;
    }
}
