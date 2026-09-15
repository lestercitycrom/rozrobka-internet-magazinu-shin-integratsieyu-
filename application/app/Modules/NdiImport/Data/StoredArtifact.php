<?php

namespace App\Modules\NdiImport\Data;

use Carbon\CarbonImmutable;

final readonly class StoredArtifact
{
    public function __construct(
        public string $id,
        public int $supplierId,
        public string $originalFilename,
        public string $storageKey,
        public int $byteSize,
        public string $sha256,
        public string $contentType,
        public CarbonImmutable $receivedAt,
    ) {}
}
