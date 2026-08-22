<?php

namespace App\Modules\Catalog\ReadModel;

use App\Modules\Catalog\Enums\ProductType;

final readonly class CatalogItem
{
    /** @param list<CatalogPrice> $prices @param array<string, mixed>|null $tire @param array<string, mixed>|null $wheel */
    public function __construct(
        public string $sku,
        public ProductType $productType,
        public string $brand,
        public string $modelName,
        public string $title,
        public array $prices,
        public CatalogStock $stock,
        public ?array $tire,
        public ?array $wheel,
        public ?array $primaryMedia = null,
        public string $supplierCode = 'ndi',
        public string $integrationProfile = 'ndi_live',
        public bool $isIntegrationTest = false,
        public ?string $supplierArticleId = null,
    ) {}
}
