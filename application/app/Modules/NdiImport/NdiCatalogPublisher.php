<?php

namespace App\Modules\NdiImport;

use App\Modules\Catalog\Enums\CatalogState;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Enums\PublicationStatus;
use App\Modules\Catalog\Enums\TaxMode;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\CurrentStock;
use App\Modules\Catalog\Models\EntityChange;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductMediaSource;
use App\Modules\Catalog\Models\SkuPrice;
use App\Modules\Catalog\Models\SkuPriceHistory;
use App\Modules\Catalog\Models\StockSnapshot;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\SupplierSku;
use App\Modules\Catalog\Models\TireLabel;
use App\Modules\Catalog\Models\TireSpec;
use App\Modules\Catalog\Models\Warehouse;
use App\Modules\Catalog\Models\WheelSpec;
use App\Modules\Content\Models\MediaAssignment;
use App\Modules\NdiImport\Data\PreparedDataset;
use App\Modules\NdiImport\Data\PublishSummary;
use App\Modules\NdiImport\Enums\DatasetRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RuntimeException;

class NdiCatalogPublisher
{
    private const CURRENT_PRICE_LIST_VALID_FROM = '2000-01-01 00:00:00';

    public function __construct(private readonly NdiMediaInheritanceReconciler $mediaInheritance) {}

    /**
     * @param  array<string, PreparedDataset>  $datasets
     */
    public function publish(Supplier $supplier, string $runId, array $datasets, string $currency): PublishSummary
    {
        $summary = new PublishSummary;
        $observedAt = now();

        foreach ([DatasetRole::Tires, DatasetRole::Wheels] as $role) {
            if (! isset($datasets[$role->value])) {
                continue;
            }
            $dataset = $datasets[$role->value];

            foreach ($dataset->workbook->rows() as $row) {
                $this->publishCatalogRow(
                    $supplier,
                    $runId,
                    $row,
                    $dataset->artifact->id,
                    $currency,
                    $observedAt,
                    $summary,
                );
            }
        }

        if (isset($datasets[DatasetRole::Tires->value]) || isset($datasets[DatasetRole::Wheels->value])) {
            $this->mediaInheritance->reconcile($supplier);
        }

        if (isset($datasets[DatasetRole::Stock->value])) {
            $this->publishStock(
                $supplier,
                $runId,
                $datasets[DatasetRole::Stock->value],
                $observedAt,
                $summary,
            );
        }

        return $summary;
    }

    /** @param array<string, mixed> $row */
    private function publishCatalogRow(
        Supplier $supplier,
        string $runId,
        array $row,
        string $artifactId,
        string $currency,
        mixed $observedAt,
        PublishSummary $summary,
    ): void {
        $productType = $row['role'] === DatasetRole::Tires->value ? ProductType::Tire : ProductType::Wheel;
        $brandName = trim($row['brand']);
        $brand = Brand::query()->firstOrCreate(
            ['canonical_name' => $brandName],
            ['slug' => Str::slug($brandName) ?: 'brand-'.substr(hash('sha256', $brandName), 0, 12)],
        );

        $sku = SupplierSku::query()->firstOrNew([
            'supplier_id' => $supplier->id,
            'external_sku' => $row['external_sku'],
        ]);

        $product = $sku->product;

        if ($product !== null && $product->product_type !== $productType) {
            throw new RuntimeException('An NDI SKU cannot change its product type.');
        }

        if ($product === null) {
            $product = new Product([
                'first_seen_at' => $observedAt,
                'publication_status' => PublicationStatus::Draft,
            ]);
        }

        $this->persistTracked($product, [
            'product_type' => $productType,
            'brand_id' => $brand->id,
            'model_name' => $row['model'],
            'title' => $row['source_description'],
            'last_catalog_seen_at' => $observedAt,
        ], $runId, $artifactId, $summary);

        $this->persistTracked($sku, [
            'product_id' => $product->id,
            'ean' => $row['ean'],
            'supplier_item_id' => $row['supplier_item_id'],
            'source_description' => $row['source_description'],
            'catalog_state' => CatalogState::Catalog,
        ], $runId, $artifactId, $summary);

        if ($productType === ProductType::Tire) {
            $spec = TireSpec::query()->firstOrNew(['product_id' => $product->id]);
            $this->persistTracked($spec, [
                ...$row['spec'],
                'reinforced' => $this->sourceFlag($row['spec']['reinforced']),
                'three_peak_mountain_snowflake' => $this->sourceFlag($row['spec']['three_peak_mountain_snowflake']),
                'ice_grip' => $this->sourceFlag($row['spec']['ice_grip']),
                'run_flat' => $this->sourceFlag($row['spec']['run_flat']),
            ], $runId, $artifactId, $summary);

            $label = TireLabel::query()->firstOrNew(['product_id' => $product->id]);
            $this->persistTracked($label, $row['label'], $runId, $artifactId, $summary);
        } else {
            $spec = WheelSpec::query()->firstOrNew(['product_id' => $product->id]);
            $this->persistTracked($spec, $row['spec'], $runId, $artifactId, $summary);
        }

        $this->publishMediaSource($product, $row['media_url'] ?? null, $artifactId);

        foreach ([TaxMode::Gross, TaxMode::Net] as $taxMode) {
            $priceList = PriceList::query()->firstOrCreate([
                'supplier_id' => $supplier->id,
                'code' => 'ndi-file-current',
                'currency' => $currency,
                'tax_mode' => $taxMode,
                'valid_from' => self::CURRENT_PRICE_LIST_VALID_FROM,
            ]);
            $amount = $row['prices'][$taxMode->value];
            $currentPrice = SkuPrice::query()->firstOrNew([
                'supplier_sku_id' => $sku->id,
                'price_list_id' => $priceList->id,
            ]);

            $this->persistTracked($currentPrice, [
                'amount' => $amount,
                'observed_at' => $observedAt,
                'source_artifact_id' => $artifactId,
            ], $runId, $artifactId, $summary);

            SkuPriceHistory::query()->firstOrCreate([
                'source_artifact_id' => $artifactId,
                'supplier_sku_id' => $sku->id,
                'price_list_id' => $priceList->id,
            ], [
                'amount' => $amount,
                'observed_at' => $observedAt,
            ]);
        }
    }

    private function publishMediaSource(Product $product, ?string $sourceUrl, string $artifactId): void
    {
        if ($sourceUrl === null) {
            $source = ProductMediaSource::query()->where('product_id', $product->id)->first();

            if ($source !== null && $source->source_kind !== NdiMediaInheritanceReconciler::INHERITED_SOURCE_KIND) {
                MediaAssignment::query()
                    ->where('assignable_type', $product::class)
                    ->where('assignable_id', $product->id)
                    ->where('role', 'primary')
                    ->where('media_asset_id', $source->media_asset_id)
                    ->delete();
                $source->delete();
            }

            return;
        }

        $source = ProductMediaSource::query()->firstOrNew(['product_id' => $product->id]);

        $newArtifactRetriesFailedSource = $source->exists
            && $source->status === 'failed'
            && $source->source_artifact_id !== $artifactId;

        if ($source->exists && ($source->source_url !== $sourceUrl || $newArtifactRetriesFailedSource)) {
            $source->status = 'pending';
            $source->last_error = null;
            $source->attempt_count = 0;
            $source->last_attempted_at = null;
            $source->downloaded_at = null;
        }

        $source->source_url = $sourceUrl;
        $source->source_kind = NdiMediaInheritanceReconciler::EXPLICIT_SOURCE_KIND;
        $source->source_artifact_id = $artifactId;
        $source->save();
    }

    private function publishStock(
        Supplier $supplier,
        string $runId,
        PreparedDataset $dataset,
        mixed $observedAt,
        PublishSummary $summary,
    ): void {
        $warehouses = collect($this->warehouseNames())->mapWithKeys(function (string $name, string $code) use ($supplier): array {
            $warehouse = Warehouse::query()->firstOrCreate([
                'supplier_id' => $supplier->id,
                'code' => $code,
            ], [
                'name' => $name,
                'country_code' => 'NO',
                'is_active' => true,
            ]);

            return [$code => $warehouse];
        });
        $seenSkuIds = [];

        foreach ($dataset->workbook->rows() as $row) {
            $sku = SupplierSku::query()->firstOrNew([
                'supplier_id' => $supplier->id,
                'external_sku' => $row['external_sku'],
            ]);

            if (! $sku->exists) {
                $this->persistTracked($sku, [
                    'product_id' => null,
                    'catalog_state' => CatalogState::StockOnly,
                ], $runId, $dataset->artifact->id, $summary);
            }

            $seenSkuIds[] = $sku->id;

            foreach ($row['locations'] as $warehouseCode => $quantity) {
                /** @var Warehouse $warehouse */
                $warehouse = $warehouses[$warehouseCode];
                $this->publishStockValue(
                    $sku,
                    $warehouse,
                    $quantity,
                    $runId,
                    $dataset->artifact->id,
                    $observedAt,
                    $summary,
                );
            }
        }

        SupplierSku::query()
            ->where('supplier_id', $supplier->id)
            ->when($seenSkuIds !== [], fn ($query) => $query->whereNotIn('id', $seenSkuIds))
            ->orderBy('id')
            ->chunkById(250, function ($skus) use ($warehouses, $runId, $dataset, $observedAt, $summary): void {
                foreach ($skus as $sku) {
                    foreach ($warehouses as $warehouse) {
                        $this->publishStockValue(
                            $sku,
                            $warehouse,
                            0,
                            $runId,
                            $dataset->artifact->id,
                            $observedAt,
                            $summary,
                        );
                    }
                }
            });
    }

    private function publishStockValue(
        SupplierSku $sku,
        Warehouse $warehouse,
        int $quantity,
        string $runId,
        string $artifactId,
        mixed $observedAt,
        PublishSummary $summary,
    ): void {
        StockSnapshot::query()->firstOrCreate([
            'source_artifact_id' => $artifactId,
            'supplier_sku_id' => $sku->id,
            'warehouse_id' => $warehouse->id,
        ], [
            'quantity' => $quantity,
            'observed_at' => $observedAt,
            'import_run_id' => $runId,
        ]);

        $current = CurrentStock::query()->firstOrNew([
            'supplier_sku_id' => $sku->id,
            'warehouse_id' => $warehouse->id,
        ]);
        $this->persistTracked($current, [
            'quantity' => $quantity,
            'observed_at' => $observedAt,
            'source_artifact_id' => $artifactId,
        ], $runId, $artifactId, $summary);
    }

    /** @param array<string, mixed> $attributes */
    private function persistTracked(
        Model $model,
        array $attributes,
        string $runId,
        string $artifactId,
        PublishSummary $summary,
    ): void {
        $exists = $model->exists;
        $before = $exists ? $model->getRawOriginal() : null;
        $model->fill($attributes);
        $dirty = $model->getDirty();

        if ($exists && $dirty === []) {
            $summary->skipped++;

            return;
        }

        $model->save();
        $exists ? $summary->updated++ : $summary->created++;

        EntityChange::query()->create([
            'import_run_id' => $runId,
            'entity_type' => class_basename($model),
            'entity_id' => (string) $model->getKey(),
            'operation' => $exists ? 'updated' : 'created',
            'before_json' => $before,
            'after_json' => $model->getAttributes(),
            'field_diff_json' => $dirty,
            'source_artifact_id' => $artifactId,
            'occurred_at' => now(),
        ]);
    }

    private function sourceFlag(?string $value): ?bool
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return ! in_array(mb_strtoupper(trim($value)), ['0', 'N', 'NO', 'FALSE', 'NEI'], true);
    }

    /** @return array<string, string> */
    private function warehouseNames(): array
    {
        return [
            'norway' => 'Norway total',
            'vestby' => 'Vestby',
            'kristiansand' => 'Kristiansand',
            'trondheim' => 'Trondheim',
            'tromso' => 'Tromsø',
            'bergen' => 'Bergen',
            'kopervik' => 'Kopervik',
        ];
    }
}
