<?php

namespace App\Modules\Catalog\Presentation;

use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\ReadModel\CatalogQuery;
use Illuminate\Http\Request;

/** One canonical, applied filter state for the query, controls, chips and links. */
final readonly class CatalogFilterState
{
    private const TIRE_DIMENSIONS = ['width' => 'widths', 'aspect' => 'aspects', 'rim' => 'rims'];

    private const WHEEL_DIMENSIONS = [
        'width' => 'widths', 'diameter' => 'diameters', 'bolt_holes' => 'bolt_holes',
        'pcd' => 'pcds', 'et' => 'ets', 'center_bore' => 'center_bores',
    ];

    /** @param array<string, string> $filters */
    private function __construct(
        private ProductType $productType,
        public array $filters,
        private int $page,
    ) {}

    public static function fromRequest(Request $request, ProductType $productType): self
    {
        $filters = [];
        foreach (['q', 'brand', ...($productType === ProductType::Tire ? ['season'] : [])] as $field) {
            $value = self::text($request->query($field));
            if ($value !== null) {
                $filters[$field] = $value;
            }
        }

        foreach (array_keys(self::dimensions($productType)) as $field) {
            $value = self::dimension($request->query($field), $field);
            if ($value !== null) {
                $filters[$field] = $value;
            }
        }

        foreach (['in_stock', 'has_image'] as $field) {
            if (self::text($request->query($field)) === '1') {
                $filters[$field] = '1';
            }
        }
        $sort = self::text($request->query('sort'));
        if (in_array($sort, ['title', 'brand'], true)) {
            $filters['sort'] = $sort;
        }

        return new self($productType, $filters, (int) (self::positiveInteger($request->query('page')) ?? 1));
    }

    public function query(): CatalogQuery
    {
        $tire = $this->productType === ProductType::Tire;
        $filters = $this->filters;

        return new CatalogQuery(
            productType: $this->productType,
            brand: $filters['brand'] ?? null,
            search: $filters['q'] ?? null,
            tireWidthMm: $tire ? ($filters['width'] ?? null) : null,
            tireAspectRatio: $tire ? ($filters['aspect'] ?? null) : null,
            tireRimDiameterIn: $tire ? ($filters['rim'] ?? null) : null,
            tireSeason: $tire ? ($filters['season'] ?? null) : null,
            wheelWidthIn: $tire ? null : ($filters['width'] ?? null),
            wheelDiameterIn: $tire ? null : ($filters['diameter'] ?? null),
            wheelBoltHoles: isset($filters['bolt_holes']) ? (int) $filters['bolt_holes'] : null,
            wheelPcdMm: $filters['pcd'] ?? null,
            wheelOffsetEtMm: $filters['et'] ?? null,
            wheelCenterBoreMm: $filters['center_bore'] ?? null,
            inStockOnly: ($filters['in_stock'] ?? null) === '1',
            hasImageOnly: ($filters['has_image'] ?? null) === '1',
            sort: $filters['sort'] ?? null,
            page: $this->page,
        );
    }

    /**
     * Preserve a valid requested value even when this catalogue has no matching facet.
     * These options remain enabled, so the next GET cannot silently remove a filter.
     *
     * @param  array<string, list<string>>  $facets
     * @return array<string, list<string>>
     */
    public function options(array $facets): array
    {
        $options = [];
        foreach (self::dimensions($this->productType) as $field => $key) {
            $values = array_map(fn ($value): ?string => self::dimension($value, $field), $facets[$key] ?? []);
            $values[] = $this->filters[$field] ?? null;
            $values = array_values(array_unique(array_filter($values, fn (?string $value): bool => $value !== null)));
            usort($values, fn (string $left, string $right): int => (float) $left <=> (float) $right);
            $options[$key] = $values;
        }

        foreach (['brand' => 'brands', ...($this->productType === ProductType::Tire ? ['season' => 'seasons'] : [])] as $field => $key) {
            $values = array_map(self::text(...), $facets[$key] ?? []);
            $values[] = $this->filters[$field] ?? null;
            $options[$key] = array_values(array_unique(array_filter($values, fn (?string $value): bool => $value !== null)));
        }

        return $options;
    }

    public static function text(mixed $input): ?string
    {
        if (! is_string($input) && ! is_int($input) && ! is_float($input)) {
            return null;
        }

        $value = mb_substr(trim((string) $input), 0, 100);

        return $value === '' ? null : $value;
    }

    /** @return array<string, string> */
    private static function dimensions(ProductType $productType): array
    {
        return $productType === ProductType::Tire ? self::TIRE_DIMENSIONS : self::WHEEL_DIMENSIONS;
    }

    private static function dimension(mixed $input, string $field): ?string
    {
        if ($field === 'bolt_holes') {
            return self::positiveInteger($input);
        }
        $value = self::text($input);
        if ($value === null || preg_match('/^(-?)(\d{1,9})(?:[.,](\d{1,2}))?$/D', $value, $matches) !== 1) {
            return null;
        }

        $whole = ltrim($matches[2], '0');
        $fraction = rtrim($matches[3] ?? '', '0');
        $number = ($whole === '' ? '0' : $whole).($fraction === '' ? '' : '.'.$fraction);
        if ($field !== 'et' && ($matches[1] === '-' || $number === '0')) {
            return null;
        }

        return $matches[1] === '-' && $number !== '0' ? '-'.$number : $number;
    }

    private static function positiveInteger(mixed $input): ?string
    {
        $value = self::text($input);
        if ($value === null || preg_match('/^\d{1,9}$/D', $value) !== 1) {
            return null;
        }
        $value = ltrim($value, '0');

        return $value === '' ? null : $value;
    }
}
