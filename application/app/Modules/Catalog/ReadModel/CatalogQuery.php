<?php

namespace App\Modules\Catalog\ReadModel;

use App\Modules\Catalog\Enums\ProductType;

final readonly class CatalogQuery
{
    public function __construct(
        public ?ProductType $productType = null,
        public ?string $brand = null,
        public ?string $search = null,
        public ?string $tireWidthMm = null,
        public ?string $tireAspectRatio = null,
        public ?string $tireRimDiameterIn = null,
        public ?string $tireSeason = null,
        public ?string $wheelWidthIn = null,
        public ?string $wheelDiameterIn = null,
        public ?int $wheelBoltHoles = null,
        public ?string $wheelPcdMm = null,
        public ?string $wheelOffsetEtMm = null,
        public ?string $wheelCenterBoreMm = null,
        public bool $inStockOnly = false,
        public bool $hasImageOnly = false,
        public ?string $sort = null,
        public int $page = 1,
        public int $perPage = 24,
    ) {}

    public function normalizedPage(): int
    {
        return max(1, $this->page);
    }

    public function normalizedPerPage(): int
    {
        return min(100, max(1, $this->perPage));
    }
}
