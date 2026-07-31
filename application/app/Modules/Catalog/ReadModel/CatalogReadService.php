<?php

namespace App\Modules\Catalog\ReadModel;

use App\Modules\Catalog\Enums\CatalogState;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Enums\PublicationStatus;
use App\Modules\Catalog\Enums\TaxMode;
use App\Modules\Catalog\Models\CurrentStock;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\SkuPrice;
use App\Modules\Catalog\Models\SupplierSku;
use App\Modules\Content\Models\MediaAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

final class CatalogReadService
{
    public function search(CatalogQuery $filters): CatalogPage
    {
        $paginator = $this->baseQuery($filters)
            ->orderBy('products.id')
            ->orderBy('supplier_skus.id')
            ->paginate($filters->normalizedPerPage(), $this->columns(), 'page', $filters->normalizedPage());

        return new CatalogPage(
            items: $this->hydrateItems($paginator->items()),
            total: $paginator->total(),
            page: $paginator->currentPage(),
            perPage: $paginator->perPage(),
        );
    }

    public function findPublicSku(string $sku): ?CatalogItem
    {
        $row = $this->baseQuery()
            ->where('supplier_skus.external_sku', $sku)
            ->orderBy('supplier_skus.id')
            ->first($this->columns());

        return $row === null ? null : $this->hydrateItems([$row])[0];
    }

    /** @return Builder<SupplierSku> */
    private function baseQuery(?CatalogQuery $filters = null): Builder
    {
        $query = SupplierSku::query()
            ->join('products', 'products.id', '=', 'supplier_skus.product_id')
            ->join('suppliers', 'suppliers.id', '=', 'supplier_skus.supplier_id')
            ->join('brands', 'brands.id', '=', 'products.brand_id')
            ->leftJoin('tire_specs', 'tire_specs.product_id', '=', 'products.id')
            ->leftJoin('wheel_specs', 'wheel_specs.product_id', '=', 'products.id')
            ->where('products.publication_status', PublicationStatus::Published->value)
            ->where('suppliers.is_active', true)
            ->where('supplier_skus.catalog_state', CatalogState::Catalog->value);

        if ($filters === null) {
            return $query;
        }

        $query->when($filters->productType, fn (Builder $q, $type) => $q->where('products.product_type', $type->value))
            ->when($filters->brand, fn (Builder $q, $brand) => $q->where('brands.canonical_name', $brand))
            ->when($filters->search, function (Builder $q, string $search): void {
                $q->where(function (Builder $nested) use ($search): void {
                    $nested->where('supplier_skus.external_sku', $search)
                        ->orWhere('products.title', 'like', "%{$search}%")
                        ->orWhere('products.model_name', 'like', "%{$search}%")
                        ->orWhere('brands.canonical_name', 'like', "%{$search}%");
                });
            })
            ->when($filters->tireWidthMm, fn (Builder $q, $value) => $q->where('tire_specs.width_mm', $value))
            ->when($filters->tireAspectRatio, fn (Builder $q, $value) => $q->where('tire_specs.aspect_ratio', $value))
            ->when($filters->tireRimDiameterIn, fn (Builder $q, $value) => $q->where('tire_specs.rim_diameter_in', $value))
            ->when($filters->tireSeason, fn (Builder $q, $value) => $q->where('tire_specs.season', $value))
            ->when($filters->wheelWidthIn, fn (Builder $q, $value) => $q->where('wheel_specs.width_in', $value))
            ->when($filters->wheelDiameterIn, fn (Builder $q, $value) => $q->where('wheel_specs.diameter_in', $value))
            ->when($filters->wheelBoltHoles, fn (Builder $q, $value) => $q->where('wheel_specs.bolt_holes', $value))
            ->when($filters->wheelPcdMm, fn (Builder $q, $value) => $q->where('wheel_specs.pcd_mm', $value))
            ->when($filters->wheelOffsetEtMm, fn (Builder $q, $value) => $q->where('wheel_specs.offset_et_mm', $value))
            ->when($filters->wheelCenterBoreMm, fn (Builder $q, $value) => $q->where('wheel_specs.center_bore_mm', $value));

        return $query;
    }

    /** @return list<string> */
    private function columns(): array
    {
        return [
            'supplier_skus.*',
            'products.product_type as catalog_product_type', 'products.model_name as catalog_model_name', 'products.title as catalog_title',
            'brands.canonical_name as catalog_brand',
            'tire_specs.width_mm as tire_width_mm', 'tire_specs.aspect_ratio as tire_aspect_ratio', 'tire_specs.rim_diameter_in as tire_rim_diameter_in', 'tire_specs.season as tire_season',
            'wheel_specs.width_in as wheel_width_in', 'wheel_specs.diameter_in as wheel_diameter_in', 'wheel_specs.bolt_holes as wheel_bolt_holes', 'wheel_specs.pcd_mm as wheel_pcd_mm', 'wheel_specs.offset_et_mm as wheel_offset_et_mm', 'wheel_specs.center_bore_mm as wheel_center_bore_mm',
        ];
    }

    /** @param list<SupplierSku> $skus @return list<CatalogItem> */
    private function hydrateItems(array $skus): array
    {
        if ($skus === []) {
            return [];
        }

        $ids = collect($skus)->pluck('id');
        $productIds = collect($skus)->pluck('product_id');
        $prices = SkuPrice::query()
            ->join('price_lists', 'price_lists.id', '=', 'sku_prices.price_list_id')
            ->whereIn('sku_prices.supplier_sku_id', $ids)
            ->where('price_lists.tax_mode', TaxMode::Gross->value)
            ->where('price_lists.valid_from', '<=', now())
            ->where(function (Builder $query): void {
                $query->whereNull('price_lists.valid_to')->orWhere('price_lists.valid_to', '>', now());
            })
            ->orderByDesc('price_lists.valid_from')
            ->get(['sku_prices.supplier_sku_id', 'sku_prices.amount', 'sku_prices.observed_at', 'price_lists.currency', 'price_lists.tax_mode', 'price_lists.code'])
            ->groupBy('supplier_sku_id');
        $stocks = CurrentStock::query()
            ->join('warehouses', 'warehouses.id', '=', 'current_stock.warehouse_id')
            ->where('warehouses.is_active', true)
            ->whereIn('current_stock.supplier_sku_id', $ids)
            ->selectRaw(
                'current_stock.supplier_sku_id, '
                ."MAX(CASE WHEN warehouses.code = 'norway' THEN current_stock.quantity END) as national_quantity, "
                ."SUM(CASE WHEN warehouses.code <> 'norway' THEN current_stock.quantity ELSE 0 END) as location_quantity, "
                .'MAX(current_stock.observed_at) as observed_at'
            )
            ->groupBy('current_stock.supplier_sku_id')
            ->get()
            ->keyBy('supplier_sku_id');
        $media = MediaAssignment::query()
            ->join('media_assets', 'media_assets.id', '=', 'media_assignments.media_asset_id')
            ->where('media_assignments.assignable_type', Product::class)
            ->where('media_assignments.role', 'primary')
            ->whereIn('media_assignments.assignable_id', $productIds)
            ->get(['media_assignments.assignable_id', 'media_assets.disk', 'media_assets.path', 'media_assets.alt_nb', 'media_assets.alt_ru'])
            ->keyBy('assignable_id');

        return array_map(function (SupplierSku $sku) use ($prices, $stocks, $media): CatalogItem {
            $stock = $stocks->get($sku->id);
            $asset = $media->get($sku->product_id);
            $quantity = $stock === null
                ? 0
                : (int) ($stock->national_quantity ?? $stock->location_quantity ?? 0);

            return new CatalogItem(
                sku: $sku->external_sku,
                productType: ProductType::from($sku->getAttribute('catalog_product_type')),
                brand: $sku->getAttribute('catalog_brand'),
                modelName: $sku->getAttribute('catalog_model_name'),
                title: $sku->getAttribute('catalog_title'),
                prices: $prices->get($sku->id, collect())
                    ->unique(fn ($price) => $price->code.'|'.$price->currency)
                    ->map(fn ($price) => new CatalogPrice($price->amount, $price->currency, TaxMode::from($price->tax_mode), $price->observed_at, $price->code))
                    ->values()
                    ->all(),
                stock: new CatalogStock($quantity, $stock?->observed_at),
                tire: $sku->getAttribute('tire_rim_diameter_in') === null ? null : ['width_mm' => $sku->getAttribute('tire_width_mm'), 'aspect_ratio' => $sku->getAttribute('tire_aspect_ratio'), 'rim_diameter_in' => $sku->getAttribute('tire_rim_diameter_in'), 'season' => $sku->getAttribute('tire_season')],
                wheel: $sku->getAttribute('wheel_diameter_in') === null ? null : ['width_in' => $sku->getAttribute('wheel_width_in'), 'diameter_in' => $sku->getAttribute('wheel_diameter_in'), 'bolt_holes' => $sku->getAttribute('wheel_bolt_holes'), 'pcd_mm' => $sku->getAttribute('wheel_pcd_mm'), 'offset_et_mm' => $sku->getAttribute('wheel_offset_et_mm'), 'center_bore_mm' => $sku->getAttribute('wheel_center_bore_mm')],
                primaryMedia: $asset === null ? null : ['url' => Storage::disk($asset->disk)->url($asset->path), 'alt_nb' => $asset->alt_nb, 'alt_ru' => $asset->alt_ru],
            );
        }, $skus);
    }
}
