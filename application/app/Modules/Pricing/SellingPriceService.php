<?php

namespace App\Modules\Pricing;

use App\Modules\Catalog\ReadModel\CatalogItem;
use App\Modules\Pricing\Data\SellingPrice;
use App\Modules\Pricing\Enums\PricingRuleScope;
use App\Modules\Pricing\Models\PricingRule;
use App\Modules\Pricing\ValueObjects\Money;
use ArithmeticError;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Throwable;

final class SellingPriceService
{
    public function forItem(CatalogItem $item): ?SellingPrice
    {
        return $this->calculate($item, $this->activeRules());
    }

    /** @param iterable<CatalogItem> $items @return list<SellingPrice|null> */
    public function forItems(iterable $items): array
    {
        $rules = $this->activeRules();
        $prices = [];

        foreach ($items as $item) {
            $prices[] = $this->calculate($item, $rules);
        }

        return $prices;
    }

    /** @return Collection<int, PricingRule> */
    private function activeRules(): Collection
    {
        return PricingRule::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderByDesc('priority')
            ->get();
    }

    /** @param Collection<int, PricingRule> $rules */
    private function calculate(CatalogItem $item, Collection $rules): ?SellingPrice
    {
        foreach ($this->matchingRules($rules, $item) as $rule) {
            $source = collect($item->prices)->first(fn ($price): bool => $price->priceListCode === $rule->source_price_list_code
                && strtoupper($price->currency) === strtoupper($rule->currency)
                && $price->taxMode === $rule->source_tax_mode
            );

            // A lower-priority rule is an intentional fallback only for a source that is
            // absent. This supports the approved policy: Gross first, Net only when
            // Gross is unavailable, without inventing VAT, fees or markup values.
            if ($source === null) {
                continue;
            }
            if (! $this->isFresh($source->observedAt)) {
                return null;
            }

            try {
                $sourceMoney = Money::fromDecimal($source->amount, strtoupper($source->currency));
                $sellingMoney = $sourceMoney
                    ->addBasisPoints($rule->markup_basis_points)
                    ->addMinor($rule->fixed_markup_minor)
                    ->roundUpTo($rule->rounding_increment_minor);
            } catch (InvalidArgumentException|ArithmeticError) {
                return null;
            }

            if ($sellingMoney->minor < 1) {
                return null;
            }

            return new SellingPrice(
                money: $sellingMoney,
                sourceMoney: $sourceMoney,
                ruleId: $rule->id,
                ruleName: $rule->name,
                sourcePriceListCode: $source->priceListCode,
                sourceObservedAt: $source->observedAt,
            );
        }

        return null;
    }

    /** @param Collection<int, PricingRule> $rules @return Collection<int, PricingRule> */
    private function matchingRules(Collection $rules, CatalogItem $item): Collection
    {
        return $rules
            ->filter(fn (PricingRule $rule): bool => $this->matches($rule, $item))
            ->sort(function (PricingRule $left, PricingRule $right): int {
                $priority = $right->priority <=> $left->priority;
                if ($priority !== 0) {
                    return $priority;
                }

                $specificity = $right->scope->specificity() <=> $left->scope->specificity();
                if ($specificity !== 0) {
                    return $specificity;
                }

                $taxMode = $left->source_tax_mode->value <=> $right->source_tax_mode->value;

                return $taxMode !== 0 ? $taxMode : $left->id <=> $right->id;
            });
    }

    private function isFresh(?string $observedAt): bool
    {
        if ($observedAt === null || trim($observedAt) === '') {
            return false;
        }

        try {
            return CarbonImmutable::parse($observedAt)->betweenIncluded(
                now()->subMinutes((int) config('storefront.price_max_age_minutes', 1560)),
                now()->addMinutes(5),
            );
        } catch (Throwable) {
            return false;
        }
    }

    private function matches(PricingRule $rule, CatalogItem $item): bool
    {
        return match ($rule->scope) {
            PricingRuleScope::Global => $rule->scope_key === null || $rule->scope_key === '',
            PricingRuleScope::ProductType => $rule->scope_key === $item->productType->value,
            PricingRuleScope::Brand => mb_strtolower((string) $rule->scope_key) === mb_strtolower($item->brand),
            PricingRuleScope::Sku => $rule->scope_key === $item->sku,
        };
    }
}
