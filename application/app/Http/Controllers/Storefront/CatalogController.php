<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Presentation\CatalogFilterState;
use App\Modules\Catalog\Presentation\CatalogItemPresenter;
use App\Modules\Catalog\ReadModel\CatalogReadService;
use App\Modules\Pricing\SellingPriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function tires(Request $request, CatalogReadService $catalog): View
    {
        return $this->index($request, $catalog, ProductType::Tire);
    }

    public function wheels(Request $request, CatalogReadService $catalog): View
    {
        return $this->index($request, $catalog, ProductType::Wheel);
    }

    public function show(string $sku, CatalogReadService $catalog, SellingPriceService $pricing): View
    {
        $item = $catalog->findPublicSku($sku);

        abort_if($item === null, 404);

        return view('storefront.catalog.show', [
            'item' => $item,
            'presenter' => new CatalogItemPresenter($item),
            'sellingPrice' => $pricing->forDisplay($item),
        ]);
    }

    public function suggestions(Request $request, CatalogReadService $catalog, ProductType $productType): JsonResponse
    {
        return response()->json(['suggestions' => $catalog->suggestions($productType, CatalogFilterState::text($request->query('q')) ?? '')]);
    }

    private function index(Request $request, CatalogReadService $catalog, ProductType $productType): View
    {
        $state = CatalogFilterState::fromRequest($request, $productType);
        $items = $catalog->search($state->query());

        $pricing = app(SellingPriceService::class);
        $displayPrices = $pricing->forDisplayItems($items->items);
        $sellingPrices = collect($items->items)
            ->mapWithKeys(fn ($item, int $index): array => [$item->sku => $displayPrices[$index]]);

        return view('storefront.catalog.index', [
            'items' => $items,
            'productType' => $productType,
            'sellingPrices' => $sellingPrices,
            'filterOptions' => $state->options($catalog->filterOptions($productType)),
            'filters' => $state->filters,
        ]);
    }
}
