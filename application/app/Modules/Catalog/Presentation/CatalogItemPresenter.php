<?php

namespace App\Modules\Catalog\Presentation;

use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\ReadModel\CatalogItem;

final readonly class CatalogItemPresenter
{
    public function __construct(private CatalogItem $item) {}

    public function activeSection(): string
    {
        return $this->item->productType === ProductType::Tire ? 'tires' : 'wheels';
    }

    public function dimension(): ?string
    {
        if ($this->item->productType === ProductType::Tire) {
            $width = $this->decimal($this->item->tire['width_mm'] ?? null);
            $aspect = $this->decimal($this->item->tire['aspect_ratio'] ?? null);
            $rim = $this->decimal($this->item->tire['rim_diameter_in'] ?? null);

            if ($width === null || $rim === null) {
                return null;
            }

            return $aspect === null || (float) $aspect <= 0
                ? "{$width} R{$rim}"
                : "{$width}/{$aspect} R{$rim}";
        }

        $width = $this->decimal($this->item->wheel['width_in'] ?? null);
        $diameter = $this->decimal($this->item->wheel['diameter_in'] ?? null);

        return $width !== null && $diameter !== null ? "{$width} × {$diameter}" : null;
    }

    public function boltCircle(): ?string
    {
        $holes = $this->positiveInteger($this->item->wheel['bolt_holes'] ?? null);
        $pcd = $this->decimal($this->item->wheel['pcd_mm'] ?? null);

        return $holes !== null && $pcd !== null ? "{$holes}×{$pcd}" : null;
    }

    public function season(): ?string
    {
        return match ($this->item->tire['season'] ?? null) {
            'Summer' => __('storefront.season_summer'),
            'Winter' => __('storefront.season_winter'),
            'All season' => __('storefront.season_all'),
            default => null,
        };
    }

    public function summary(): string
    {
        if ($this->item->productType === ProductType::Tire) {
            $loadIndex = $this->token($this->item->tire['load_index'] ?? null, '/^\d{2,3}(?:\/\d{2,3})?$/');
            $speedSymbol = $this->token($this->item->tire['speed_symbol'] ?? null, '/^[A-Z]{1,2}$/i', uppercase: true);

            return implode(' · ', array_filter([
                $this->dimension(),
                $loadIndex !== null && $speedSymbol !== null ? $loadIndex.$speedSymbol : null,
                $this->season(),
            ]));
        }

        $offset = $this->decimal($this->item->wheel['offset_et_mm'] ?? null);

        return implode(' · ', array_filter([
            $this->dimension(),
            $this->boltCircle(),
            $offset !== null ? 'ET '.$offset : null,
        ]));
    }

    /** @return list<array{label: string, value: string}> */
    public function technicalDetails(): array
    {
        return $this->item->productType === ProductType::Tire
            ? $this->tireTechnicalDetails()
            : $this->wheelTechnicalDetails();
    }

    /** @return list<array{label: string, value: string}> */
    private function tireTechnicalDetails(): array
    {
        $details = [];
        $this->append($details, 'storefront.load_index', $this->token($this->item->tire['load_index'] ?? null, '/^\d{2,3}(?:\/\d{2,3})?$/'));
        $this->append($details, 'storefront.speed_symbol', $this->token($this->item->tire['speed_symbol'] ?? null, '/^[A-Z]{1,2}$/i', uppercase: true));
        $this->append($details, 'storefront.construction', $this->construction($this->item->tire['construction'] ?? null));
        $this->appendBoolean($details, 'storefront.reinforced', $this->item->tire['reinforced'] ?? null);
        $this->appendBoolean($details, 'storefront.run_flat', $this->item->tire['run_flat'] ?? null);
        $this->appendBoolean($details, 'storefront.three_peak_mountain_snowflake', $this->item->tire['three_peak_mountain_snowflake'] ?? null);
        $this->appendBoolean($details, 'storefront.ice_grip', $this->item->tire['ice_grip'] ?? null);
        $this->append($details, 'storefront.production_year', $this->token($this->item->tire['production_year'] ?? null, '/^(?:19|20)\d{2}$/'));

        // NDI has not supplied an enum/meaning contract for WINTERTYRE. Until
        // that exists, exposing its free text risks showing a column caption or
        // a winter claim on a summer product, so it is deliberately omitted.
        return $details;
    }

    /** @return list<array{label: string, value: string}> */
    private function wheelTechnicalDetails(): array
    {
        $details = [];
        $this->append($details, 'storefront.offset', $this->decimal($this->item->wheel['offset_et_mm'] ?? null));
        $this->append($details, 'storefront.center_bore', $this->decimal($this->item->wheel['center_bore_mm'] ?? null));

        // Color and bolt-hole boring are uncontracted supplier free text. The
        // fitment decimals above remain useful and can be rendered consistently
        // in every locale; raw free text stays hidden until mappings are agreed.
        return $details;
    }

    private function construction(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = preg_replace('/\s+/', ' ', trim($value));
        if ($normalized === null || preg_match('/^(?:PCR\s+)?radial(?: tyre)?(?:\s*[<>]=?\s*\d+\s*km\/h)?$/i', $normalized) !== 1) {
            return null;
        }

        return __('storefront.construction_radial');
    }

    private function decimal(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $value = trim((string) $value);
        if (preg_match('/^-?\d+(?:\.\d+)?$/', $value) !== 1) {
            return null;
        }

        $normalized = str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;

        return $normalized === '-0' ? '0' : $normalized;
    }

    private function positiveInteger(mixed $value): ?string
    {
        $value = is_int($value) ? (string) $value : (is_string($value) ? trim($value) : '');

        return preg_match('/^[1-9]\d*$/', $value) === 1 ? $value : null;
    }

    private function token(mixed $value, string $pattern, bool $uppercase = false): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);
        if (preg_match($pattern, $value) !== 1) {
            return null;
        }

        return $uppercase ? mb_strtoupper($value) : $value;
    }

    /** @param list<array{label: string, value: string}> $details */
    private function append(array &$details, string $labelKey, ?string $value): void
    {
        if ($value !== null) {
            $details[] = ['label' => __($labelKey), 'value' => $value];
        }
    }

    /** @param list<array{label: string, value: string}> $details */
    private function appendBoolean(array &$details, string $labelKey, mixed $value): void
    {
        if (is_bool($value)) {
            $this->append($details, $labelKey, $value ? __('storefront.yes') : __('storefront.no'));
        }
    }
}
