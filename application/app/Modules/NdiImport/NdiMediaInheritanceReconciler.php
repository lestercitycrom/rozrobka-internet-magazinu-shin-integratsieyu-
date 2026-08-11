<?php

namespace App\Modules\NdiImport;

use App\Modules\Catalog\Enums\CatalogState;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductMediaSource;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Content\Models\MediaAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class NdiMediaInheritanceReconciler
{
    public const EXPLICIT_SOURCE_KIND = 'ndi_catalog';

    public const INHERITED_SOURCE_KIND = 'ndi_catalog_inherited';

    /** @return array{created: int, updated: int, removed: int} */
    public function reconcile(?Supplier $supplier = null): array
    {
        $supplier ??= Supplier::query()->where('code', config('ndi.supplier_code'))->first();
        $summary = ['created' => 0, 'updated' => 0, 'removed' => 0];

        if ($supplier === null) {
            return $summary;
        }

        $rows = DB::table('products')
            ->join('supplier_skus', 'supplier_skus.product_id', '=', 'products.id')
            ->leftJoin('product_media_sources', 'product_media_sources.product_id', '=', 'products.id')
            ->where('supplier_skus.supplier_id', $supplier->id)
            ->where('supplier_skus.catalog_state', CatalogState::Catalog->value)
            ->whereNotNull('products.model_name')
            ->select([
                'products.id as product_id',
                'products.product_type',
                'products.brand_id',
                'products.model_name',
                'product_media_sources.id as source_id',
                'product_media_sources.source_kind',
                'product_media_sources.source_url',
                'product_media_sources.source_artifact_id',
                'product_media_sources.media_asset_id',
                'product_media_sources.status as source_status',
                'product_media_sources.last_attempted_at',
                'product_media_sources.downloaded_at',
            ])
            ->distinct()
            ->orderBy('products.id')
            ->get();

        /** @var array<int, true> $retainedProductIds */
        $retainedProductIds = [];

        foreach ($rows->groupBy(fn (object $row): string => $this->groupKey($row)) as $group) {
            $donors = $group->filter(
                fn (object $row): bool => $row->source_kind === self::EXPLICIT_SOURCE_KIND
                    && is_string($row->source_url)
                    && $row->source_url !== '',
            );
            $urls = $donors->pluck('source_url')->unique()->values();

            // Conflicting source URLs are never guessed between. NDI confirmed
            // inheritance only for an exact make + model/pattern group.
            if ($urls->count() !== 1) {
                continue;
            }

            $donor = $this->preferredDonor($donors);

            foreach ($group as $row) {
                if ($row->source_kind === self::EXPLICIT_SOURCE_KIND
                    || ($row->source_kind !== null && $row->source_kind !== self::INHERITED_SOURCE_KIND)) {
                    continue;
                }

                $change = $this->reconcileTarget((int) $row->product_id, $donor);
                $retainedProductIds[(int) $row->product_id] = true;

                if ($change !== null) {
                    $summary[$change]++;
                }
            }
        }

        ProductMediaSource::query()
            ->where('source_kind', self::INHERITED_SOURCE_KIND)
            ->whereHas('product.supplierSkus', fn ($query) => $query->where('supplier_id', $supplier->id))
            ->lazyById(250)
            ->each(function (ProductMediaSource $source) use ($retainedProductIds, &$summary): void {
                if (isset($retainedProductIds[$source->product_id])) {
                    return;
                }

                $this->removeSource($source);
                $summary['removed']++;
            });

        return $summary;
    }

    private function groupKey(object $row): string
    {
        $model = preg_replace('/\s+/u', ' ', Str::lower(trim((string) $row->model_name))) ?? '';

        if ($model === '') {
            return 'unmatched|'.$row->product_id;
        }

        return $row->product_type.'|'.$row->brand_id.'|'.$model;
    }

    private function preferredDonor(Collection $donors): object
    {
        return $donors
            ->sortByDesc(fn (object $row): int => ($row->source_status === 'downloaded' && $row->media_asset_id !== null ? 1_000_000_000 : 0) + (int) $row->source_id)
            ->first();
    }

    /** @return 'created'|'updated'|null */
    private function reconcileTarget(int $productId, object $donor): ?string
    {
        $source = ProductMediaSource::query()->firstOrNew(['product_id' => $productId]);

        if ($source->exists && $source->source_kind !== self::INHERITED_SOURCE_KIND) {
            return null;
        }

        $created = ! $source->exists;
        $previousAssetId = $source->media_asset_id;
        $urlChanged = ! $source->exists || $source->source_url !== $donor->source_url;
        $donorIsDownloaded = $donor->source_status === 'downloaded' && $donor->media_asset_id !== null;

        $source->fill([
            'source_kind' => self::INHERITED_SOURCE_KIND,
            'source_url' => $donor->source_url,
            'source_artifact_id' => $donor->source_artifact_id,
        ]);

        if ($donorIsDownloaded) {
            $source->fill([
                'media_asset_id' => $donor->media_asset_id,
                'status' => 'downloaded',
                'attempt_count' => 0,
                'last_error' => null,
                'last_attempted_at' => $donor->last_attempted_at,
                'downloaded_at' => $donor->downloaded_at,
            ]);
        } elseif ($urlChanged) {
            $source->fill([
                'media_asset_id' => null,
                'status' => 'pending',
                'attempt_count' => 0,
                'last_error' => null,
                'last_attempted_at' => null,
                'downloaded_at' => null,
            ]);
        }

        $changed = $source->isDirty();
        $source->save();

        if ($source->is_suppressed) {
            $this->removeMatchingPrimary($productId, $previousAssetId);
        } elseif ($donorIsDownloaded) {
            $this->assignDownloadedAsset($productId, $source, $previousAssetId);
        } elseif ($urlChanged) {
            $this->removeMatchingPrimary($productId, $previousAssetId);
        }

        return $created ? 'created' : ($changed ? 'updated' : null);
    }

    private function assignDownloadedAsset(int $productId, ProductMediaSource $source, ?string $previousAssetId): void
    {
        $primary = MediaAssignment::query()
            ->where('assignable_type', Product::class)
            ->where('assignable_id', $productId)
            ->where('role', 'primary')
            ->first();

        if ($primary !== null && $primary->media_asset_id !== $previousAssetId) {
            return;
        }

        MediaAssignment::query()->updateOrCreate(
            ['assignable_type' => Product::class, 'assignable_id' => $productId, 'role' => 'primary'],
            ['media_asset_id' => $source->media_asset_id],
        );
    }

    private function removeSource(ProductMediaSource $source): void
    {
        $this->removeMatchingPrimary($source->product_id, $source->media_asset_id);
        $source->delete();
    }

    private function removeMatchingPrimary(int $productId, ?string $assetId): void
    {
        if ($assetId === null) {
            return;
        }

        MediaAssignment::query()
            ->where('assignable_type', Product::class)
            ->where('assignable_id', $productId)
            ->where('role', 'primary')
            ->where('media_asset_id', $assetId)
            ->delete();
    }
}
