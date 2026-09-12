<?php

namespace App\Http\Controllers\Admin;

use App\Modules\Commerce\Models\PublicServicePrice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class PublicServicePriceController
{
    public function index()
    {
        return view('admin.public-service-prices.index', [
            'prices' => PublicServicePrice::query()->orderBy('sort_order')->orderBy('id')->paginate(40),
        ]);
    }

    public function create()
    {
        return view('admin.public-service-prices.form', ['price' => new PublicServicePrice]);
    }

    public function store(Request $request): RedirectResponse
    {
        $price = PublicServicePrice::create($this->validated($request));

        return redirect()->route('admin.public-service-prices.edit', $price)->with('status', __('public_service_prices.saved'));
    }

    public function edit(PublicServicePrice $publicServicePrice)
    {
        return view('admin.public-service-prices.form', ['price' => $publicServicePrice]);
    }

    public function update(Request $request, PublicServicePrice $publicServicePrice): RedirectResponse
    {
        $validated = $this->validated($request, $publicServicePrice);
        $manifest = $publicServicePrice->customer_homepage_composition_manifest;
        if (is_array($manifest) && ($manifest['state'] ?? null) === 'applied' && is_array($manifest['changes']['price_minor'] ?? null)) {
            $manifest['changes']['price_minor']['operator_owned'] = true;
        }

        $publicServicePrice->fill([
            ...$validated,
            'manifest_hash' => null,
        ]);
        if (is_array($manifest)) {
            $publicServicePrice->customer_homepage_composition_manifest = $manifest;
        }
        $publicServicePrice->save();

        return redirect()->route('admin.public-service-prices.edit', $publicServicePrice)->with('status', __('public_service_prices.saved'));
    }

    public function destroy(PublicServicePrice $publicServicePrice): RedirectResponse
    {
        $publicServicePrice->delete();

        return redirect()->route('admin.public-service-prices.index')->with('status', __('public_service_prices.deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?PublicServicePrice $price = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('public_service_prices', 'code')->ignore($price)],
            'category_code' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'category_title_nb' => ['required', 'string', 'max:255'],
            'category_title_ru' => ['required', 'string', 'max:255'],
            'title_nb' => ['required', 'string', 'max:255'],
            'title_ru' => ['required', 'string', 'max:255'],
            'description_nb' => ['nullable', 'string', 'max:2000'],
            'description_ru' => ['nullable', 'string', 'max:2000'],
            'price_nok' => ['required', 'regex:/^\d{1,7}(?:[.,]\d{1,2})?$/'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'between:-100000,100000'],
        ]);

        $data['price_minor'] = $this->minor((string) $data['price_nok']);
        if ($request->boolean('is_active') && $data['price_minor'] < 1) {
            throw ValidationException::withMessages(['price_nok' => __('public_service_prices.positive_price')]);
        }
        $data['currency'] = 'NOK';
        $data['is_active'] = $request->boolean('is_active');
        unset($data['price_nok']);

        return $data;
    }

    private function minor(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', str_replace(',', '.', $value), 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
