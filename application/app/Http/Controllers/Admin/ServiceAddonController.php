<?php

namespace App\Http\Controllers\Admin;

use App\Modules\Commerce\Enums\ServiceAddonApplicability;
use App\Modules\Commerce\Enums\ServiceAddonBillingMode;
use App\Modules\Commerce\Models\ServiceAddon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ServiceAddonController
{
    public function index()
    {
        return view('admin.service-addons.index', ['addons' => ServiceAddon::query()->orderBy('sort_order')->orderBy('code')->paginate(30)]);
    }

    public function edit(ServiceAddon $serviceAddon)
    {
        return view('admin.service-addons.form', ['addon' => $serviceAddon, 'applicabilities' => ServiceAddonApplicability::cases(), 'billingModes' => ServiceAddonBillingMode::cases()]);
    }

    public function update(Request $request, ServiceAddon $serviceAddon)
    {
        $serviceAddon->update($this->validated($request));

        return redirect()->route('admin.service-addons.edit', $serviceAddon)->with('status', __('service_addons.saved'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title_nb' => ['required', 'string', 'max:255'], 'title_ru' => ['required', 'string', 'max:255'],
            'applicability' => ['required', Rule::enum(ServiceAddonApplicability::class)],
            'billing_mode' => ['required', Rule::enum(ServiceAddonBillingMode::class)],
            'price_nok' => ['nullable', 'regex:/^\d{1,7}(?:[.,]\d{1,2})?$/'],
            'is_active' => ['nullable', 'boolean'], 'sort_order' => ['required', 'integer', 'between:-100000,100000'],
        ]);
        $data['currency'] = 'NOK';
        $data['price_minor'] = blank($data['price_nok'] ?? null) ? null : $this->minor((string) $data['price_nok']);
        $data['is_active'] = $request->boolean('is_active');
        if ($data['is_active'] && ($data['price_minor'] === null || $data['price_minor'] <= 0)) {
            throw ValidationException::withMessages(['price_nok' => __('service_addons.positive_price_required')]);
        }
        unset($data['price_nok']);

        return $data;
    }

    private function minor(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', str_replace(',', '.', $value), 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
