<?php

namespace App\Modules\Catalog\ReadModel;

final readonly class CatalogPage
{
    /** @param list<CatalogItem> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}
}
