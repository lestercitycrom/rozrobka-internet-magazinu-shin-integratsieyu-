<?php

namespace App\Modules\Catalog\ReadModel;

enum CatalogStockState: string
{
    case Missing = 'missing';
    case Stale = 'stale';
    case OutOfStock = 'out_of_stock';
    case Local = 'local';
    case Remote = 'remote';
}
