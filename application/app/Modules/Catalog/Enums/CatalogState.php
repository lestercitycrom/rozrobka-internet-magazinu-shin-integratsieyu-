<?php

namespace App\Modules\Catalog\Enums;

enum CatalogState: string
{
    case Catalog = 'catalog';
    case StockOnly = 'stock_only';
    case NeedsEnrichment = 'needs_enrichment';
    case Missing = 'missing';
    case Inactive = 'inactive';
}
