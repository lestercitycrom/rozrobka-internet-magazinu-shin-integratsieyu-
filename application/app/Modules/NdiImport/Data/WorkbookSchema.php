<?php

namespace App\Modules\NdiImport\Data;

use App\Modules\NdiImport\Enums\DatasetRole;

final readonly class WorkbookSchema
{
    /** @param list<string> $headers */
    public function __construct(
        public DatasetRole $role,
        public array $headers,
        public string $sheetName,
    ) {}
}
