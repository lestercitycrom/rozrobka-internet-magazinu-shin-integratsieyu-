<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\ReadModel\CatalogQuery;
use App\Modules\Catalog\ReadModel\CatalogReadService;
use App\Modules\Pricing\SellingPriceService;
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

        return view('storefront.catalog.show', ['item' => $item, 'sellingPrice' => $pricing->forItem($item)]);
    }

    private function index(Request $request, CatalogReadService $catalog, ProductType $productType): View
    {
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
            wheelBoltHoles: $productType === ProductType::Wheel ? ($request->integer('bolt_holes') ?: null) : null,
            wheelPcdMm: $productType === ProductType::Wheel ? $this->decimalInput($request, 'pcd') : null,
            wheelOffsetEtMm: $productType === ProductType::Wheel ? $this->decimalInput($request, 'et') : null,
            wheelCenterBoreMm: $productType === ProductType::Wheel ? $this->decimalInput($request, 'center_bore') : null,
            page: max(1, $request->integer('page', 1)),
        ));

        $pricing = app(SellingPriceService::class);
        $sellingPrices = collect($items->items)
            ->mapWithKeys(fn ($item): array => [$item->sku => $pricing->forItem($item)]);

        return view('storefront.catalog.index', [
            'items' => $items,
            'productType' => $productType,
            'sellingPrices' => $sellingPrices,
        ]);
    }

    private function input(Request $request, string $key): ?string
    {
        $value = trim((string) $request->input($key));

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
            return $value;
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return $whole.'.'.str_pad($fraction, 2, '0');
    }
}
