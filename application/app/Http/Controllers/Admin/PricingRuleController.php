<?php

namespace App\Http\Controllers\Admin;

use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Enums\TaxMode;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\SupplierSku;
use App\Modules\Pricing\Enums\PricingRuleScope;
use App\Modules\Pricing\Models\PricingRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class PricingRuleController
{
    public function index()
    {
        $rules = PricingRule::query()->orderByDesc('is_active')->orderBy('priority')->paginate(30);

        return view('admin.pricing-rules.index', compact('rules'));
    }

    public function create()
    {
        return view('admin.pricing-rules.form', [
            'rule' => new PricingRule,
            'scopes' => PricingRuleScope::cases(),
            'taxModes' => TaxMode::cases(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->assertUnambiguous($data);
        $rule = PricingRule::create($data);

        return redirect()->route('admin.pricing-rules.edit', $rule)->with('status', __('pricing.saved'));
    }

    public function edit(PricingRule $pricingRule)
    {
        return view('admin.pricing-rules.form', [
            'rule' => $pricingRule,
            'scopes' => PricingRuleScope::cases(),
            'taxModes' => TaxMode::cases(),
        ]);
    }

    public function update(Request $request, PricingRule $pricingRule)
    {
        $data = $this->validated($request);
        $this->assertUnambiguous($data, $pricingRule);
        $pricingRule->update($data);

        return redirect()->route('admin.pricing-rules.edit', $pricingRule)->with('status', __('pricing.saved'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'scope' => ['required', Rule::enum(PricingRuleScope::class)], 'scope_key' => ['nullable', 'string', 'max:255'], 'source_price_list_code' => ['required', 'string', 'max:64'], 'source_tax_mode' => ['required', Rule::enum(TaxMode::class)], 'currency' => ['required', 'string', 'size:3', 'alpha'], 'markup_percent' => ['required', 'regex:/^\d{1,3}(?:[.,]\d{1,2})?$/'], 'fixed_markup_nok' => ['required', 'regex:/^\d{1,7}(?:[.,]\d{1,2})?$/'], 'rounding_nok' => ['required', 'regex:/^\d{1,5}(?:[.,]\d{1,2})?$/'], 'priority' => ['required', 'integer', 'between:-100000,100000'], 'is_active' => ['nullable', 'boolean'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at']]);
        if ($data['scope'] === PricingRuleScope::Global->value) {
            $data['scope_key'] = null;
        } elseif (blank($data['scope_key'] ?? null)) {
            throw ValidationException::withMessages(['scope_key' => __('pricing.scope_key_required')]);
        } else {
            $data['scope_key'] = trim((string) $data['scope_key']);
        }
        $data['currency'] = strtoupper($data['currency']);
        $data['is_active'] = $request->boolean('is_active');
        $this->assertScopeTargetExists($data);
        $data['markup_basis_points'] = $this->minor($data['markup_percent'], 100);
        $data['fixed_markup_minor'] = $this->minor($data['fixed_markup_nok'], 100);
        $data['rounding_increment_minor'] = $this->minor($data['rounding_nok'], 100);
        if ($data['markup_basis_points'] > 50000) {
            throw ValidationException::withMessages(['markup_percent' => __('pricing.markup_max')]);
        }
        if ($data['fixed_markup_minor'] > 100000000) {
            throw ValidationException::withMessages(['fixed_markup_nok' => __('pricing.fixed_markup_max')]);
        }
        if ($data['rounding_increment_minor'] < 1) {
            throw ValidationException::withMessages(['rounding_nok' => __('pricing.rounding_min')]);
        }
        if ($data['rounding_increment_minor'] > 1000000) {
            throw ValidationException::withMessages(['rounding_nok' => __('pricing.rounding_max')]);
        }
        unset($data['markup_percent'],$data['fixed_markup_nok'],$data['rounding_nok']);

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function assertScopeTargetExists(array &$data): void
    {
        if (! $data['is_active'] || $data['scope'] === PricingRuleScope::Global->value) {
            return;
        }

        $key = (string) $data['scope_key'];
        $exists = match ($data['scope']) {
            PricingRuleScope::ProductType->value => ProductType::tryFrom(mb_strtolower($key)) !== null,
            PricingRuleScope::Brand->value => Brand::query()->whereRaw('LOWER(canonical_name) = ?', [mb_strtolower($key)])->exists(),
            PricingRuleScope::Sku->value => SupplierSku::query()->where('external_sku', $key)->exists(),
            default => false,
        };

        if (! $exists) {
            throw ValidationException::withMessages(['scope_key' => __('pricing.scope_key_invalid')]);
        }

        if ($data['scope'] === PricingRuleScope::ProductType->value) {
            $data['scope_key'] = mb_strtolower($key);
        } elseif ($data['scope'] === PricingRuleScope::Brand->value) {
            $data['scope_key'] = Brand::query()
                ->whereRaw('LOWER(canonical_name) = ?', [mb_strtolower($key)])
                ->value('canonical_name');
        }
    }

    private function minor(string $value, int $factor): int
    {
        $value = str_replace(',', '.', $value);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        [$whole,$fraction] = array_pad(explode('.', $value, 2), 2, '');
        $number = ((int) $whole * $factor) + (int) str_pad($fraction, 2, '0');

        return $negative ? -$number : $number;
    }

    private function assertUnambiguous(array $data, ?PricingRule $ignore = null): void
    {
        if (! $data['is_active']) {
            return;
        }

        $query = PricingRule::query()
            ->where('is_active', true)
            ->where('priority', $data['priority'])
            ->where('scope', $data['scope'])
            ->where('source_price_list_code', $data['source_price_list_code'])
            ->where('source_tax_mode', $data['source_tax_mode'])
            ->where('currency', $data['currency'])
            ->when(
                $data['scope_key'] === null,
                fn ($builder) => $builder->whereNull('scope_key'),
                fn ($builder) => $builder->where('scope_key', $data['scope_key']),
            )
            ->when($ignore, fn ($builder) => $builder->whereKeyNot($ignore->id));

        $start = $data['starts_at'] ?? null;
        $end = $data['ends_at'] ?? null;
        $query
            ->when($end !== null, fn ($builder) => $builder->where(
                fn ($overlap) => $overlap->whereNull('starts_at')->orWhere('starts_at', '<', $end)
            ))
            ->when($start !== null, fn ($builder) => $builder->where(
                fn ($overlap) => $overlap->whereNull('ends_at')->orWhere('ends_at', '>', $start)
            ));

        if ($query->exists()) {
            throw ValidationException::withMessages(['priority' => __('pricing.overlap')]);
        }
    }
}
