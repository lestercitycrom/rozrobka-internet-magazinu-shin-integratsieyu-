<?php

namespace App\Modules\Catalog\ReadModel;

use App\Modules\Catalog\Models\SupplierSku;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class PublicCatalogAvailability
{
    /** @param Builder<SupplierSku> $query */
    public static function constrainToFreshPositiveStock(Builder $query): Builder
    {
        [$oldestAllowedAt, $latestAllowedAt] = StockFreshnessPolicy::bounds();

        return $query->where(function (Builder $available) use ($oldestAllowedAt, $latestAllowedAt): void {
            $available->whereExists(function (QueryBuilder $stock) use ($oldestAllowedAt, $latestAllowedAt): void {
                $stock->selectRaw('1')
                    ->from('current_stock')
                    ->join('warehouses', 'warehouses.id', '=', 'current_stock.warehouse_id')
                    ->whereColumn('current_stock.supplier_sku_id', 'supplier_skus.id')
                    ->where('warehouses.is_active', true)
                    ->where('warehouses.code', 'norway')
                    ->where('current_stock.quantity', '>', 0)
                    ->whereBetween('current_stock.observed_at', [$oldestAllowedAt, $latestAllowedAt]);
            })->orWhere(function (Builder $physical) use ($oldestAllowedAt, $latestAllowedAt): void {
                $physical->whereNotExists(function (QueryBuilder $national): void {
                    $national->selectRaw('1')
                        ->from('current_stock')
                        ->join('warehouses', 'warehouses.id', '=', 'current_stock.warehouse_id')
                        ->whereColumn('current_stock.supplier_sku_id', 'supplier_skus.id')
                        ->where('warehouses.is_active', true)
                        ->where('warehouses.code', 'norway');
                })->whereExists(function (QueryBuilder $stock) use ($oldestAllowedAt, $latestAllowedAt): void {
                    $stock->selectRaw('1')
                        ->from('current_stock')
                        ->join('warehouses', 'warehouses.id', '=', 'current_stock.warehouse_id')
                        ->whereColumn('current_stock.supplier_sku_id', 'supplier_skus.id')
                        ->where('warehouses.is_active', true)
                        ->where('warehouses.code', '!=', 'norway')
                        ->where('current_stock.quantity', '>', 0)
                        ->whereBetween('current_stock.observed_at', [$oldestAllowedAt, $latestAllowedAt]);
                })->whereNotExists(function (QueryBuilder $invalid) use ($oldestAllowedAt, $latestAllowedAt): void {
                    $invalid->selectRaw('1')
                        ->from('current_stock')
                        ->join('warehouses', 'warehouses.id', '=', 'current_stock.warehouse_id')
                        ->whereColumn('current_stock.supplier_sku_id', 'supplier_skus.id')
                        ->where('warehouses.is_active', true)
                        ->where('warehouses.code', '!=', 'norway')
                        ->where(function (QueryBuilder $timestamp) use ($oldestAllowedAt, $latestAllowedAt): void {
                            $timestamp->where('current_stock.observed_at', '<', $oldestAllowedAt)
                                ->orWhere('current_stock.observed_at', '>', $latestAllowedAt);
                        });
                });
            });
        });
    }
}
