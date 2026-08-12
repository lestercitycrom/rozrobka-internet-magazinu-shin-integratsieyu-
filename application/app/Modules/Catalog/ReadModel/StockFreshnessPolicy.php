<?php

namespace App\Modules\Catalog\ReadModel;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Throwable;

final class StockFreshnessPolicy
{
    private const FUTURE_TOLERANCE_MINUTES = 5;

    public static function isFresh(?string $observedAt): bool
    {
        if ($observedAt === null || trim($observedAt) === '') {
            return false;
        }

        try {
            [$oldestAllowedAt, $latestAllowedAt] = self::bounds();

            return CarbonImmutable::parse($observedAt)
                ->betweenIncluded($oldestAllowedAt, $latestAllowedAt);
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public static function bounds(?CarbonInterface $referenceTime = null): array
    {
        $now = $referenceTime === null
            ? CarbonImmutable::instance(now())
            : CarbonImmutable::instance($referenceTime);

        return [
            $now->subMinutes(max(0, (int) config('storefront.stock_max_age_minutes', 180))),
            $now->addMinutes(self::FUTURE_TOLERANCE_MINUTES),
        ];
    }

    /** @template T of EloquentBuilder|QueryBuilder
     * @param  T  $query
     * @return T
     */
    public static function constrain(EloquentBuilder|QueryBuilder $query, string $observedAtColumn): EloquentBuilder|QueryBuilder
    {
        [$oldestAllowedAt, $latestAllowedAt] = self::bounds();

        return $query->whereBetween($observedAtColumn, [$oldestAllowedAt, $latestAllowedAt]);
    }
}
