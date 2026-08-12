<?php

namespace App\Modules\Catalog\Publication;

use App\Modules\Catalog\Enums\CatalogState;
use App\Modules\Catalog\Enums\PublicationStatus;
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
        $options = Product::query()
            ->where('publication_status', PublicationStatus::Draft->value)
            ->with(['brand', 'supplierSkus.supplier', 'supplierSkus.prices.priceList'])
            ->orderBy('id')
            ->get()
            ->flatMap(function (Product $product): array {
                return $product->supplierSkus
                    ->filter(fn ($sku): bool => $sku->catalog_state === CatalogState::Catalog && (bool) $sku->supplier?->is_active)
                    ->map(function ($sku) use ($product): array {
                        $prices = $sku->prices->filter(fn ($price): bool => $price->priceList !== null
                            && $price->priceList->valid_from <= now()
                            && ($price->priceList->valid_to === null || $price->priceList->valid_to > now()))
                            ->map(fn ($price) => new CatalogPrice($price->amount, $price->priceList->currency, $price->priceList->tax_mode, $price->observed_at, $price->priceList->code))
                            ->values()
                            ->all();

                        $item = new CatalogItem($sku->external_sku, $product->product_type, $product->brand->canonical_name, $product->model_name, $product->title, $prices, new CatalogStock(0, null), null, null);

                        return ['product' => $product, 'item' => $item];
                    })
                    ->values()
                    ->all();
            })
            ->values();

        $prices = $this->pricing->forItems($options->pluck('item'));

        return $options
            ->map(fn (array $option, int $index): array => [
                'product' => $option['product'],
                'price' => $prices[$index],
            ])
            ->filter(fn (array $candidate): bool => $candidate['price'] !== null && $candidate['price']->money->minor > 0)
            ->unique(fn (array $candidate): int => $candidate['product']->id)
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
