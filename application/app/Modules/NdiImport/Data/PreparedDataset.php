<?php

namespace App\Modules\NdiImport\Data;

use App\Modules\NdiImport\Enums\DatasetRole;

final readonly class PreparedDataset
{
    public function __construct(
        public DatasetRole $role,
        public StoredArtifact $artifact,
        public ValidatedWorkbook $workbook,
    ) {}
}
