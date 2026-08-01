<?php

namespace App\Modules\Catalog\Publication;

use App\Modules\Catalog\Enums\CatalogState;
use App\Modules\Catalog\Enums\PublicationStatus;
use App\Modules\Catalog\Enums\TaxMode;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\ReadModel\CatalogItem;
use App\Modules\Catalog\ReadModel\CatalogPrice;
use App\Modules\Catalog\ReadModel\CatalogStock;
use App\Modules\Pricing\Data\SellingPrice;
use App\Modules\Pricing\SellingPriceService;
use Illuminate\Support\Collection;

final class EligibleCatalogPublication
{
    public function __construct(private readonly SellingPriceService $pricing) {}

    /** @return Collection<int, array{product: Product, price: SellingPrice}> */
    public function preview(): Collection
    {
        return Product::query()
            ->where('publication_status', PublicationStatus::Draft->value)
            ->with(['brand', 'supplierSkus.supplier', 'supplierSkus.prices.priceList'])
            ->orderBy('id')
            ->get()
            ->map(function (Product $product): ?array {
                foreach ($product->supplierSkus as $sku) {
                    if ($sku->catalog_state !== CatalogState::Catalog || ! $sku->supplier?->is_active) {
                        continue;
                    }

                    $prices = $sku->prices->filter(fn ($price): bool => $price->priceList !== null
                        && $price->priceList->tax_mode === TaxMode::Gross
                        && $price->priceList->valid_from <= now()
                        && ($price->priceList->valid_to === null || $price->priceList->valid_to > now()))
                        ->map(fn ($price) => new CatalogPrice($price->amount, $price->priceList->currency, TaxMode::Gross, $price->observed_at, $price->priceList->code))
                        ->values()
                        ->all();

                    $item = new CatalogItem($sku->external_sku, $product->product_type, $product->brand->canonical_name, $product->model_name, $product->title, $prices, new CatalogStock(0, null), null, null);
                    $price = $this->pricing->forItem($item);
                    if ($price !== null && $price->money->minor > 0) {
                        return ['product' => $product, 'price' => $price];
                    }
                }

                return null;
            })
            ->filter()
            ->values();
    }

    public function publish(): int
    {
        $ids = $this->preview()->pluck('product.id');

        return $ids->isEmpty() ? 0 : Product::query()
            ->whereIn('id', $ids)
            ->where('publication_status', PublicationStatus::Draft->value)
            ->update(['publication_status' => PublicationStatus::Published->value, 'updated_at' => now()]);
    }
}
