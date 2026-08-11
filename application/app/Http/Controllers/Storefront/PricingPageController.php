<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\ServiceAddon;
use Illuminate\View\View;

final class PricingPageController extends Controller
{
    public function __invoke(): View
    {
        $addons = ServiceAddon::query()->available()->orderBy('sort_order')->orderBy('id')->get();

        return view('storefront.pricing.index', compact('addons'));
    }
}
