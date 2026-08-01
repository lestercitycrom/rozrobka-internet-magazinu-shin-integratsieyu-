<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Presentation\CatalogItemPresenter;
use App\Modules\Catalog\ReadModel\CatalogQuery;
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
            'sellingPrice' => $pricing->forItem($item),
        ]);
    }

    public function suggestions(Request $request, CatalogReadService $catalog, ProductType $productType): JsonResponse
    {
        return response()->json(['suggestions' => $catalog->suggestions($productType, $this->input($request, 'q') ?? '')]);
    }

    private function index(Request $request, CatalogReadService $catalog, ProductType $productType): View
    {
        $sort = $this->input($request, 'sort');
        $inStockOnly = $this->input($request, 'in_stock') === '1';
        $hasImageOnly = $this->input($request, 'has_image') === '1';
        $page = $this->positiveIntegerInput($request, 'page') ?? 1;
        $items = $catalog->search(new CatalogQuery(
            productType: $productType,
            brand: $this->input($request, 'brand'),
            search: $this->input($request, 'q'),
            tireWidthMm: $productType === ProductType::Tire ? $this->decimalInput($request, 'width') : null,
            tireAspectRatio: $productType === ProductType::Tire ? $this->decimalInput($request, 'aspect') : null,
            tireRimDiameterIn: $productType === ProductType::Tire ? $this->decimalInput($request, 'rim') : null,
            tireSeason: $productType === ProductType::Tire ? $this->input($request, 'season') : null,
            wheelWidthIn: $productType === ProductType::Wheel ? $this->decimalInput($request, 'width') : null,
            wheelDiameterIn: $productType === ProductType::Wheel ? $this->decimalInput($request, 'diameter') : null,
            wheelBoltHoles: $productType === ProductType::Wheel ? $this->positiveIntegerInput($request, 'bolt_holes') : null,
            wheelPcdMm: $productType === ProductType::Wheel ? $this->decimalInput($request, 'pcd') : null,
            wheelOffsetEtMm: $productType === ProductType::Wheel ? $this->decimalInput($request, 'et') : null,
            wheelCenterBoreMm: $productType === ProductType::Wheel ? $this->decimalInput($request, 'center_bore') : null,
            inStockOnly: $inStockOnly,
            hasImageOnly: $hasImageOnly,
            sort: in_array($sort, ['title', 'brand'], true) ? $sort : null,
            page: $page,
        ));

        $pricing = app(SellingPriceService::class);
        $sellingPrices = collect($items->items)
            ->mapWithKeys(fn ($item): array => [$item->sku => $pricing->forItem($item)]);

        $filterKeys = ['q', 'brand', 'width', 'aspect', 'rim', 'season', 'diameter', 'bolt_holes', 'pcd', 'et', 'center_bore'];
        $filters = collect($filterKeys)
            ->mapWithKeys(fn (string $key): array => [$key => $this->input($request, $key)])
            ->filter(fn (?string $value): bool => $value !== null)
            ->all();

        if ($inStockOnly) {
            $filters['in_stock'] = '1';
        }
        if ($hasImageOnly) {
            $filters['has_image'] = '1';
        }
        if (in_array($sort, ['title', 'brand'], true)) {
            $filters['sort'] = $sort;
        }

        return view('storefront.catalog.index', [
            'items' => $items,
            'productType' => $productType,
            'sellingPrices' => $sellingPrices,
            'filterOptions' => $catalog->filterOptions($productType),
            'filters' => $filters,
        ]);
    }

    private function input(Request $request, string $key): ?string
    {
        $input = $request->query($key);

        if (! is_string($input) && ! is_numeric($input)) {
            return null;
        }

        $value = mb_substr(trim((string) $input), 0, 100);

        return $value === '' ? null : $value;
    }

    private function decimalInput(Request $request, string $key): ?string
    {
        $value = $this->input($request, $key);
        if ($value === null) {
            return null;
        }

        $value = str_replace(',', '.', $value);
        if (preg_match('/^-?\d+(?:\.\d{1,2})?$/', $value) !== 1) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return $whole.'.'.str_pad($fraction, 2, '0');
    }

    private function positiveIntegerInput(Request $request, string $key): ?int
    {
        $value = $this->input($request, $key);

        if ($value === null || preg_match('/^[1-9]\d{0,8}$/', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }
}
