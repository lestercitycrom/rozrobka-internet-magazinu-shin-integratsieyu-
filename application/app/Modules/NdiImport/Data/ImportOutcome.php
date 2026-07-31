<?php

namespace App\Modules\NdiImport\Data;

final readonly class ImportOutcome
{
    public function __construct(
        public string $runId,
        public string $status,
        public int $createdCount,
        public int $updatedCount,
        public int $skippedCount,
        public int $rejectedCount,
    ) {}

    public function published(): bool
    {
        return $this->status === 'succeeded';
    }
}
