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
        $query = $this->baseQuery($filters);
        if ($filters->inStockOnly) {
            PublicCatalogAvailability::constrainToFreshPositiveStock($query);
        }
        if ($filters->hasImageOnly) {
            $query->whereExists(function ($media): void {
                $media->selectRaw('1')
                    ->from('media_assignments')
                    ->join('media_assets', 'media_assets.id', '=', 'media_assignments.media_asset_id')
                    ->whereColumn('media_assignments.assignable_id', 'products.id')
                    ->where('media_assignments.assignable_type', Product::class)
                    ->where('media_assignments.role', 'primary')
                    ->where('media_assets.is_active', true);
            });
        }
        if ($filters->sort === 'brand') {
            $query->orderBy('brands.canonical_name')->orderBy('products.title');
        } elseif ($filters->sort === 'title') {
            $query->orderBy('products.title')->orderBy('brands.canonical_name');
        } else {
            $query->orderBy('products.id');
        }

        $paginator = $query
            ->orderBy('supplier_skus.id')
            ->paginate($filters->normalizedPerPage(), $this->columns(), 'page', $filters->normalizedPage());

        return new CatalogPage(
            items: $this->hydrateItems($paginator->items()),
            total: $paginator->total(),
            page: $paginator->currentPage(),
            perPage: $paginator->perPage(),
        );
    }

    /** @return array<string, list<string>> */
    public function filterOptions(ProductType $productType): array
    {
        $query = $this->baseQuery(new CatalogQuery(productType: $productType));
        $values = fn (string $column): array => (clone $query)->whereNotNull($column)->distinct()->orderBy($column)->pluck($column)->map(fn ($value) => (string) $value)->all();
        $options = ['brands' => $values('brands.canonical_name')];

        return $productType === ProductType::Tire
            ? $options + ['widths' => $values('tire_specs.width_mm'), 'aspects' => $values('tire_specs.aspect_ratio'), 'rims' => $values('tire_specs.rim_diameter_in'), 'seasons' => $values('tire_specs.season')]
            : $options + ['widths' => $values('wheel_specs.width_in'), 'diameters' => $values('wheel_specs.diameter_in'), 'bolt_holes' => $values('wheel_specs.bolt_holes'), 'pcds' => $values('wheel_specs.pcd_mm'), 'ets' => $values('wheel_specs.offset_et_mm'), 'center_bores' => $values('wheel_specs.center_bore_mm')];
    }

    /** @return list<array{sku: string, title: string, brand: string}> */
    public function suggestions(ProductType $productType, string $term): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }

        return $this->baseQuery(new CatalogQuery(productType: $productType))
            ->where(function (Builder $query) use ($term): void {
                $query->where('supplier_skus.external_sku', 'like', $term.'%')->orWhere('supplier_skus.supplier_item_id', 'like', $term.'%')->orWhere('products.title', 'like', '%'.$term.'%')->orWhere('products.model_name', 'like', '%'.$term.'%')->orWhere('brands.canonical_name', 'like', '%'.$term.'%');
            })->orderByRaw('CASE WHEN supplier_skus.external_sku = ? THEN 0 ELSE 1 END', [$term])->orderBy('products.title')->limit(8)
            ->get(['supplier_skus.external_sku as sku', 'products.title as title', 'brands.canonical_name as brand'])
            ->map(fn ($row): array => ['sku' => $row->sku, 'title' => $row->title, 'brand' => $row->brand])->all();
    }

    public function findPublicSku(string $sku): ?CatalogItem
    {
        $row = $this->baseQuery()
            ->where('supplier_skus.external_sku', $sku)
            ->orderBy('supplier_skus.id')
            ->first($this->columns());

        return $row === null ? null : $this->hydrateItems([$row])[0];
    }

    /** @return list<string> */
    public function publicSkuCodes(): array
    {
        return $this->baseQuery()
            ->where('suppliers.is_test_fixture', false)
            ->orderBy('supplier_skus.id')
            ->pluck('supplier_skus.external_sku')
            ->map(fn ($sku): string => (string) $sku)
            ->all();
    }

    /** @return Builder<SupplierSku> */
    private function baseQuery(?CatalogQuery $filters = null): Builder
    {
        $query = SupplierSku::query()
            ->join('products', 'products.id', '=', 'supplier_skus.product_id')
            ->join('suppliers', 'suppliers.id', '=', 'supplier_skus.supplier_id')
            ->join('brands', 'brands.id', '=', 'products.brand_id')
            ->leftJoin('tire_specs', 'tire_specs.product_id', '=', 'products.id')
            ->leftJoin('tire_labels', 'tire_labels.product_id', '=', 'products.id')
            ->leftJoin('wheel_specs', 'wheel_specs.product_id', '=', 'products.id')
            ->where('suppliers.is_active', true)
            ->where('products.publication_status', PublicationStatus::Published->value)
            ->where('supplier_skus.catalog_state', CatalogState::Catalog->value);

        // Publication is product identity. Stock freshness only controls purchasing and the
        // explicit "in stock" filter; a delayed feed must never erase the browse catalogue.
        if (app()->environment('production')) {
            $query->where('suppliers.is_test_fixture', false);
        }

        if ($filters === null) {
            return $query;
        }

        $query->when($filters->productType, fn (Builder $q, $type) => $q->where('products.product_type', $type->value))
            ->when($filters->brand, fn (Builder $q, $brand) => $q->where('brands.canonical_name', $brand))
            ->when($filters->search, function (Builder $q, string $search): void {
                $q->where(function (Builder $nested) use ($search): void {
                    $nested->where('supplier_skus.external_sku', $search)
                        ->orWhere('supplier_skus.supplier_item_id', $search)
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
            'suppliers.code as catalog_supplier_code', 'suppliers.integration_profile as catalog_integration_profile', 'suppliers.is_test_fixture as catalog_is_test_fixture',
            'products.product_type as catalog_product_type', 'products.model_name as catalog_model_name', 'products.title as catalog_title',
            'brands.canonical_name as catalog_brand',
            'tire_specs.width_mm as tire_width_mm', 'tire_specs.width_inch as tire_width_inch', 'tire_specs.aspect_ratio as tire_aspect_ratio', 'tire_specs.rim_diameter_in as tire_rim_diameter_in',
            'tire_specs.load_index as tire_load_index', 'tire_specs.speed_symbol as tire_speed_symbol', 'tire_specs.construction as tire_construction', 'tire_specs.season as tire_season',
            'tire_specs.reinforced as tire_reinforced', 'tire_specs.winter_type as tire_winter_type', 'tire_specs.three_peak_mountain_snowflake as tire_3pmsf',
            'tire_specs.ice_grip as tire_ice_grip', 'tire_specs.run_flat as tire_run_flat', 'tire_specs.production_year as tire_production_year',
            'tire_labels.rolling_resistance as tire_rolling_resistance', 'tire_labels.wet_grip as tire_wet_grip', 'tire_labels.noise_db as tire_noise_db',
            'tire_labels.noise_class as tire_noise_class', 'tire_labels.eu_tyre_class as tire_eu_tyre_class', 'tire_labels.eu_label_regulation as tire_eu_label_regulation',
            'wheel_specs.width_in as wheel_width_in', 'wheel_specs.diameter_in as wheel_diameter_in', 'wheel_specs.bolt_holes as wheel_bolt_holes', 'wheel_specs.pcd_mm as wheel_pcd_mm',
            'wheel_specs.offset_et_mm as wheel_offset_et_mm', 'wheel_specs.center_bore_mm as wheel_center_bore_mm', 'wheel_specs.bolt_hole_boring as wheel_bolt_hole_boring', 'wheel_specs.color as wheel_color',
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
                ."MAX(CASE WHEN warehouses.code = 'norway' THEN current_stock.observed_at END) as national_observed_at, "
                ."SUM(CASE WHEN warehouses.code = 'tromso' THEN current_stock.quantity ELSE 0 END) as local_quantity, "
                ."MAX(CASE WHEN warehouses.code = 'tromso' THEN current_stock.observed_at END) as local_observed_at, "
                ."SUM(CASE WHEN warehouses.code NOT IN ('norway', 'tromso') THEN current_stock.quantity ELSE 0 END) as remote_location_quantity, "
                ."MIN(CASE WHEN warehouses.code != 'norway' THEN current_stock.observed_at END) as physical_oldest_observed_at, "
                ."MAX(CASE WHEN warehouses.code != 'norway' THEN current_stock.observed_at END) as physical_latest_observed_at"
            )
            ->groupBy('current_stock.supplier_sku_id')
            ->get()
            ->keyBy('supplier_sku_id');
        $media = MediaAssignment::query()
            ->join('media_assets', 'media_assets.id', '=', 'media_assignments.media_asset_id')
            ->where('media_assignments.assignable_type', Product::class)
            ->where('media_assignments.role', 'primary')
            ->where('media_assets.is_active', true)
            ->whereIn('media_assignments.assignable_id', $productIds)
            ->get(['media_assignments.assignable_id', 'media_assets.disk', 'media_assets.path', 'media_assets.alt_nb', 'media_assets.alt_ru'])
            ->keyBy('assignable_id');

        $nullableBool = static function (mixed $value): ?bool {
            if ($value === null || $value === '') {
                return null;
            }

            return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        };

        return array_map(function (SupplierSku $sku) use ($prices, $stocks, $media, $nullableBool): CatalogItem {
            $stock = $stocks->get($sku->id);
            $asset = $media->get($sku->product_id);
            $localObservedAt = $stock?->local_observed_at === null ? null : (string) $stock->local_observed_at;
            $rawLocalQuantity = StockFreshnessPolicy::isFresh($localObservedAt)
                ? (int) ($stock?->local_quantity ?? 0)
                : 0;
            $remoteLocationQuantity = (int) ($stock?->remote_location_quantity ?? 0);
            $physicalQuantity = $rawLocalQuantity + $remoteLocationQuantity;
            // NDI's Norway value is the contracted, capped sellable quantity. Location rows are
            // warehouse detail and must not be added on top of it.
            $nationalQuantity = $stock?->national_quantity;
            $quantity = $nationalQuantity === null ? $physicalQuantity : (int) $nationalQuantity;
            $observedAt = $nationalQuantity === null
                ? $this->aggregateObservedAt(
                    $stock?->physical_oldest_observed_at,
                    $stock?->physical_latest_observed_at,
                )
                : ($stock?->national_observed_at === null ? null : (string) $stock->national_observed_at);
            $localQuantity = min($rawLocalQuantity, $quantity);
            $remoteQuantity = max(0, $quantity - $localQuantity);

            return new CatalogItem(
                sku: $sku->external_sku,
                productType: ProductType::from($sku->getAttribute('catalog_product_type')),
                brand: $sku->getAttribute('catalog_brand'),
                modelName: $sku->getAttribute('catalog_model_name'),
                title: $sku->getAttribute('catalog_title'),
                prices: $prices->get($sku->id, collect())
                    ->unique(fn ($price) => $price->code.'|'.$price->currency.'|'.$price->tax_mode)
                    ->map(fn ($price) => new CatalogPrice($price->amount, $price->currency, TaxMode::from($price->tax_mode), $price->observed_at, $price->code))
                    ->values()
                    ->all(),
                stock: new CatalogStock($quantity, $observedAt, $localQuantity, $remoteQuantity),
                tire: $sku->getAttribute('tire_rim_diameter_in') === null ? null : [
                    'width_mm' => $sku->getAttribute('tire_width_mm'), 'width_inch' => $sku->getAttribute('tire_width_inch'),
                    'aspect_ratio' => $sku->getAttribute('tire_aspect_ratio'), 'rim_diameter_in' => $sku->getAttribute('tire_rim_diameter_in'),
                    'load_index' => $sku->getAttribute('tire_load_index'), 'speed_symbol' => $sku->getAttribute('tire_speed_symbol'),
                    'construction' => $sku->getAttribute('tire_construction'), 'season' => $sku->getAttribute('tire_season'),
                    'reinforced' => $nullableBool($sku->getAttribute('tire_reinforced')), 'winter_type' => $sku->getAttribute('tire_winter_type'),
                    'three_peak_mountain_snowflake' => $nullableBool($sku->getAttribute('tire_3pmsf')), 'ice_grip' => $nullableBool($sku->getAttribute('tire_ice_grip')),
                    'run_flat' => $nullableBool($sku->getAttribute('tire_run_flat')), 'production_year' => $sku->getAttribute('tire_production_year'),
                    'label' => [
                        'rolling_resistance' => $sku->getAttribute('tire_rolling_resistance'), 'wet_grip' => $sku->getAttribute('tire_wet_grip'),
                        'noise_db' => $sku->getAttribute('tire_noise_db'), 'noise_class' => $sku->getAttribute('tire_noise_class'),
                        'eu_tyre_class' => $sku->getAttribute('tire_eu_tyre_class'), 'regulation' => $sku->getAttribute('tire_eu_label_regulation'),
                    ],
                ],
                wheel: $sku->getAttribute('wheel_diameter_in') === null ? null : [
                    'width_in' => $sku->getAttribute('wheel_width_in'), 'diameter_in' => $sku->getAttribute('wheel_diameter_in'),
                    'bolt_holes' => $sku->getAttribute('wheel_bolt_holes'), 'pcd_mm' => $sku->getAttribute('wheel_pcd_mm'),
                    'offset_et_mm' => $sku->getAttribute('wheel_offset_et_mm'), 'center_bore_mm' => $sku->getAttribute('wheel_center_bore_mm'),
                    'bolt_hole_boring' => $sku->getAttribute('wheel_bolt_hole_boring'), 'color' => $sku->getAttribute('wheel_color'),
                ],
                primaryMedia: $asset === null ? null : [
                    'url' => $asset->disk === 'public'
                        ? '/storage/'.ltrim($asset->path, '/')
                        : Storage::disk($asset->disk)->url($asset->path),
                    'alt_nb' => $asset->alt_nb,
                    'alt_ru' => $asset->alt_ru,
                ],
                supplierCode: (string) $sku->getAttribute('catalog_supplier_code'),
                integrationProfile: (string) $sku->getAttribute('catalog_integration_profile'),
                isIntegrationTest: (bool) $sku->getAttribute('catalog_is_test_fixture'),
                supplierArticleId: filled($sku->supplier_item_id) ? (string) $sku->supplier_item_id : null,
            );
        }, $skus);
    }

    private function aggregateObservedAt(mixed $oldest, mixed $latest): ?string
    {
        if ($oldest === null || $latest === null) {
            return null;
        }

        $oldest = (string) $oldest;
        $latest = (string) $latest;

        // When physical locations are the quantity source, every contributing snapshot
        // must be current. Return the failing edge so CatalogStock remains fail-closed
        // for both stale and implausibly future timestamps.
        return StockFreshnessPolicy::isFresh($oldest) ? $latest : $oldest;
    }
}
