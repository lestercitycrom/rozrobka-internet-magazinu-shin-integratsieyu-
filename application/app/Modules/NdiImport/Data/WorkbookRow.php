<?php

namespace App\Modules\NdiImport\Data;

final readonly class WorkbookRow
{
    /** @param array<string, mixed> $values */
    public function __construct(
        public int $rowNumber,
        public array $values,
    ) {}
}
