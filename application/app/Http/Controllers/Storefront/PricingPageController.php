<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\PublicServicePrice;
use Illuminate\View\View;

final class PricingPageController extends Controller
{
    public function __invoke(): View
    {
        $prices = PublicServicePrice::query()
            ->published()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('category_code');

        return view('storefront.pricing.index', compact('prices'));
    }
}
