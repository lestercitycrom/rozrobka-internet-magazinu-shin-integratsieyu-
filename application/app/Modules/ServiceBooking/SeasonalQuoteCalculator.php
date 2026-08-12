<?php

namespace App\Modules\ServiceBooking;

use App\Modules\Commerce\Models\PublicServicePrice;

final class SeasonalQuoteCalculator
{
    /** @return array<string, int> */
    public function publicMatrix(): array
    {
        return PublicServicePrice::query()
            ->published()
            ->whereIn('code', $this->supportedCodes())
            ->pluck('price_minor', 'code')
            ->map(fn ($price): int => (int) $price)
            ->all();
    }

    /**
     * @param  list<array{rim_band: string, vehicle_type: string, storage_plan: string}>  $vehicles
     * @return array{min_minor: int, max_minor: int, currency: string}|null
     */
    public function calculate(string $fulfilment, array $vehicles): ?array
    {
        if (! in_array($fulfilment, ['workshop', 'mobile'], true) || $vehicles === []) {
            return null;
        }

        $prices = $this->publicMatrix();
        $minimum = 0;
        $maximum = 0;

        foreach ($vehicles as $vehicle) {
            $base = $this->baseRange($prices, $fulfilment, $vehicle['vehicle_type'], $vehicle['rim_band']);
            if ($base === null) {
                return null;
            }

            $storage = $vehicle['storage_plan'] === 'none'
                ? [0, 0]
                : $this->storageRange($prices, $vehicle['rim_band']);
            if ($storage === null) {
                return null;
            }

            // Storage always adds a new six-month period, including tyres already at the hotel.
            $minimum += $base[0] + $storage[0];
            $maximum += $base[1] + $storage[1];
        }

        return ['min_minor' => $minimum, 'max_minor' => $maximum, 'currency' => 'NOK'];
    }

    /** @param array<string, int> $prices @return array{int, int}|null */
    private function baseRange(array $prices, string $fulfilment, string $vehicleType, string $rimBand): ?array
    {
        if ($vehicleType !== 'passenger') {
            if ($fulfilment !== 'mobile') {
                return null;
            }

            $code = $vehicleType === 'large_vehicle' ? 'mobile-large-vehicle' : 'mobile-six-wheels';

            return isset($prices[$code]) ? [$prices[$code], $prices[$code]] : null;
        }

        return $this->rimRange($prices, $fulfilment.'-', $rimBand);
    }

    /** @param array<string, int> $prices @return array{int, int}|null */
    private function storageRange(array $prices, string $rimBand): ?array
    {
        return $this->rimRange($prices, 'hotel-', $rimBand);
    }

    /** @param array<string, int> $prices @return array{int, int}|null */
    private function rimRange(array $prices, string $prefix, string $rimBand): ?array
    {
        $suffixes = $rimBand === 'unknown' ? ['r15', 'r16-r18', 'r19'] : [$rimBand];
        $values = collect($suffixes)
            ->map(fn (string $suffix) => $prices[$prefix.$suffix] ?? null)
            ->filter(fn ($value) => is_int($value))
            ->values();

        if ($values->count() !== count($suffixes)) {
            return null;
        }

        return [(int) $values->min(), (int) $values->max()];
    }

    /** @return list<string> */
    private function supportedCodes(): array
    {
        return [
            'workshop-r15', 'workshop-r16-r18', 'workshop-r19',
            'mobile-r15', 'mobile-r16-r18', 'mobile-r19', 'mobile-large-vehicle', 'mobile-six-wheels',
            'hotel-r15', 'hotel-r16-r18', 'hotel-r19',
        ];
    }
}
