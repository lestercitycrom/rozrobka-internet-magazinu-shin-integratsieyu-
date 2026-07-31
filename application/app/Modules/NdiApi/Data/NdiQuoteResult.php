<?php

namespace App\Modules\NdiApi\Data;

final readonly class NdiQuoteResult
{
    /** @param list<NdiQuoteLine> $lines */
    public function __construct(
        public string $documentId,
        public string $errorCode,
        public array $lines,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->errorCode === '0';
    }
}
