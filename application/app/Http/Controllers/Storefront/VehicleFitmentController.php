<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\ReadModel\CatalogReadService;
use App\Modules\Content\ReadModel\StorefrontSettingsReadService;
use App\Modules\VehicleFitment\VehicleLookup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

final class VehicleFitmentController extends Controller
{
    public function create(CatalogReadService $catalog, StorefrontSettingsReadService $settings)
    {
        return view('storefront.fitment.form', ['brands' => $this->brands($catalog), 'settings' => $settings->current()]);
    }

    public function store(Request $r, VehicleLookup $lookup, CatalogReadService $catalog, StorefrontSettingsReadService $settings)
    {
        $v = $r->validate(['registration' => ['required', 'string', 'max:16']]);
        $fitment = $lookup->lookup($v['registration']);
        $registration = $lookup->normalize($v['registration']);
        if ($fitment !== null && $registration !== null) {
            $r->session()->put('vehicle_fitment.pending', [
                'registration' => $registration,
                'tyres' => $fitment->tyres,
                'source' => 'statens_vegvesen',
                'looked_up_at' => now()->toIso8601String(),
            ]);
        } else {
            $r->session()->forget(['vehicle_fitment.pending', 'vehicle_fitment.confirmed']);
        }

        return view('storefront.fitment.form', ['fitment' => $fitment, 'brands' => $this->brands($catalog), 'settings' => $settings->current()]);
    }

    public function confirm(Request $request)
    {
        $values = $request->validate([
            'width' => ['required', 'string', 'regex:/^\d{3}$/'],
            'aspect' => ['required', 'string', 'regex:/^\d{2,3}$/'],
            'rim' => ['required', 'string', 'regex:/^\d{2}$/'],
        ]);
        $pending = $request->session()->get('vehicle_fitment.pending');
        abort_unless(is_array($pending) && isset($pending['registration'], $pending['tyres']), 422);
        $confirmed = collect($pending['tyres'])->contains(fn (mixed $tyre): bool => is_array($tyre)
            && ($tyre['width'] ?? null) === $values['width']
            && ($tyre['aspect'] ?? null) === $values['aspect']
            && ($tyre['rim'] ?? null) === $values['rim']);
        abort_unless($confirmed, 422);

        $request->session()->put('vehicle_fitment.confirmed', [
            'registration' => $pending['registration'],
            'source' => 'statens_vegvesen',
            'width' => $values['width'],
            'aspect' => $values['aspect'],
            'rim' => $values['rim'],
            'confirmed_at' => now()->toIso8601String(),
        ]);
        $request->session()->forget('vehicle_fitment.pending');

        return redirect()->route($this->routePrefix().'tires.index', $values);
    }

    /** @return list<string> */
    private function brands(CatalogReadService $catalog): array
    {
        if (! Schema::hasTable('supplier_skus')) {
            return [];
        }

        try {
            return collect([
                ...$catalog->filterOptions(ProductType::Tire)['brands'],
                ...$catalog->filterOptions(ProductType::Wheel)['brands'],
            ])->unique()->sort()->take(16)->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function routePrefix(): string
    {
        return app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    }
}
