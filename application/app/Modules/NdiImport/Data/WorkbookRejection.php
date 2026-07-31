<?php

namespace App\Modules\NdiImport\Data;

final readonly class WorkbookRejection
{
    /** @param array<string, mixed> $rawRow */
    public function __construct(
        public int $rowNumber,
        public ?string $externalSku,
        public ?string $fieldName,
        public string $reason,
        public array $rawRow,
    ) {}
}
