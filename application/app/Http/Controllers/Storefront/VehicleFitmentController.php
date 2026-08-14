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
                'vehicle' => [
                    'make' => $fitment->make,
                    'model' => $fitment->model,
                ],
                'tyres' => $fitment->tyres,
                'wheels' => $fitment->wheels,
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
            'intent' => ['required', 'string', 'in:tires,wheels,packages,tpms'],
            'size' => ['required', 'string', 'regex:/^\d{3}\|\d{2,3}\|\d{2}$/'],
        ]);
        [$width, $aspect, $rim] = explode('|', $values['size']);
        $pending = $request->session()->get('vehicle_fitment.pending');
        abort_unless(is_array($pending) && isset($pending['registration'], $pending['tyres']), 422);
        $confirmed = collect($pending['tyres'])->contains(fn (mixed $tyre): bool => is_array($tyre)
            && ($tyre['width'] ?? null) === $width
            && ($tyre['aspect'] ?? null) === $aspect
            && ($tyre['rim'] ?? null) === $rim);
        abort_unless($confirmed, 422);

        $vehicle = collect([
            data_get($pending, 'vehicle.make'),
            data_get($pending, 'vehicle.model'),
        ])->filter(fn (mixed $part): bool => is_string($part) && filled($part))->implode(' ');
        $context = [
            'intent' => $values['intent'],
            'registration' => $pending['registration'],
            'vehicle' => $vehicle,
            'size' => $width.'/'.$aspect.' R'.$rim,
            'width' => $width,
            'aspect' => $aspect,
            'rim' => $rim,
            'wheels' => is_array($pending['wheels'] ?? null) ? $pending['wheels'] : [],
            'source' => $pending['source'] ?? 'statens_vegvesen',
            'compatibility_status' => $values['intent'] === 'tires'
                ? 'confirmed_tyre_size'
                : 'manual_confirmation_required',
            'confirmed_at' => now()->toIso8601String(),
        ];
        $request->session()->forget('vehicle_fitment.pending');

        if ($values['intent'] === 'tires') {
            $request->session()->put('vehicle_fitment.confirmed', $context);
            $request->session()->forget('vehicle_fitment.context');

            return redirect()->route($this->routePrefix().'tires.index', compact('width', 'aspect', 'rim'));
        }

        $request->session()->forget('vehicle_fitment.confirmed');
        $request->session()->put('vehicle_fitment.context', $context);

        if ($values['intent'] === 'wheels') {
            return redirect()->route($this->routePrefix().'wheels.index');
        }

        $slug = $values['intent'] === 'packages' ? 'dekk-felg-og-hjulpakker' : 'tpms-sensor';

        return redirect()->route($this->routePrefix().'requests.create', ['slug' => $slug]);
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
