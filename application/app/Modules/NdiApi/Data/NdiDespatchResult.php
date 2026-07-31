<?php

namespace App\Modules\NdiApi\Data;

final readonly class NdiDespatchResult
{
    /** @param list<NdiDespatchDocument> $documents */
    public function __construct(
        public string $errorCode,
        public array $documents,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->errorCode === '0';
    }
}
