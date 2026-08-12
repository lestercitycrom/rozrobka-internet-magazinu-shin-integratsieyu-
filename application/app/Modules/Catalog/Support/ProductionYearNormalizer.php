<?php

namespace App\Modules\Catalog\Support;

final class ProductionYearNormalizer
{
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = mb_strtoupper(trim((string) $value));
        if ($value === '') {
            return null;
        }

        $year = null;
        if (preg_match('/(?<!\d)((?:19|20)\d{2})(?!\d)/', $value, $match) === 1) {
            $year = (int) $match[1];
        } elseif (preg_match('/^(?:DOT\s*)?(\d{2})$/', $value, $match) === 1) {
            $year = 2000 + (int) $match[1];
        }

        if ($year === null || $year < 1990 || $year > ((int) date('Y')) + 1) {
            return null;
        }

        return (string) $year;
    }
}
