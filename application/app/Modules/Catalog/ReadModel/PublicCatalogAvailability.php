<?php

namespace App\Modules\Catalog\ReadModel;

use App\Modules\Catalog\Models\SupplierSku;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class PublicCatalogAvailability
{
    /** @param Builder<SupplierSku> $query */
    public static function constrain(Builder $query): Builder
    {
        return $query
            ->where('suppliers.is_active', true)
            ->whereExists(function (QueryBuilder $stock): void {
                $stock->selectRaw('1')
                    ->from('current_stock')
                    ->join('warehouses', 'warehouses.id', '=', 'current_stock.warehouse_id')
                    ->whereColumn('current_stock.supplier_sku_id', 'supplier_skus.id')
                    ->where('warehouses.is_active', true)
                    ->where('current_stock.quantity', '>', 0);

                StockFreshnessPolicy::constrain($stock, 'current_stock.observed_at');
            });
    }
}
