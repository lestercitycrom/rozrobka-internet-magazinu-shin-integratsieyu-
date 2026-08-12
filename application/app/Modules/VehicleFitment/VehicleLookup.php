<?php

namespace App\Modules\VehicleFitment;

use App\Modules\VehicleFitment\Data\VehicleFitment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class VehicleLookup
{
    public function normalize(string $registration): ?string
    {
        $value = strtoupper(preg_replace('/\s+/', '', trim($registration)));

        return preg_match('/^[A-Z]{2}\d{5}$/', $value) ? $value : null;
    }

    public function lookup(string $registration): ?VehicleFitment
    {
        $normalizedRegistration = $this->normalize($registration);

        if ($normalizedRegistration === null || ! config('vehicle_fitment.enabled') || blank(config('vehicle_fitment.api_key'))) {
            return null;
        }

        return Cache::remember(
            'fitment:'.hash('sha256', $normalizedRegistration),
            config('vehicle_fitment.cache_ttl'),
            function () use ($normalizedRegistration): ?VehicleFitment {
                try {
                    $response = Http::timeout(config('vehicle_fitment.timeout'))
                        ->withHeaders([
                            config('vehicle_fitment.header') => 'Apikey '.config('vehicle_fitment.api_key'),
                        ])
                        ->get(config('vehicle_fitment.endpoint'), [
                            'kjennemerke' => $normalizedRegistration,
                        ]);
                } catch (\Throwable) {
                    return null;
                }

                if (! $response->successful()) {
                    return null;
                }

                return $this->mapResponse($response->json());
            },
        );
    }

    private function mapResponse(mixed $payload): ?VehicleFitment
    {
        $vehicles = is_array($payload) ? data_get($payload, 'kjoretoydataListe', []) : [];

        if (! is_array($vehicles)) {
            return null;
        }

        foreach ($vehicles as $vehicle) {
            if (! is_array($vehicle)) {
                continue;
            }

            $technicalData = data_get($vehicle, 'godkjenning.tekniskGodkjenning.tekniskeData', []);
            if (! is_array($technicalData)) {
                continue;
            }

            $make = $this->firstText(data_get($technicalData, 'generelt.merke', []), 'merke');
            $model = $this->firstText(data_get($technicalData, 'generelt.handelsbetegnelse', []));
            $combinations = data_get($technicalData, 'dekkOgFelg.akselDekkOgFelgKombinasjon', []);
            $tyres = $this->mapTyres(is_array($combinations) ? $combinations : []);
            $wheels = $this->mapWheels(is_array($combinations) ? $combinations : []);

            if ($tyres !== [] || $wheels !== []) {
                return new VehicleFitment($make, $model, $tyres, $wheels);
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private function firstText(array $values, ?string $field = null): string
    {
        foreach ($values as $value) {
            $candidate = $field === null ? $value : (is_array($value) ? ($value[$field] ?? null) : null);

            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return '';
    }

    /**
     * @param  array<array-key, mixed>  $combinations
     * @return list<array{width:string, aspect:string, rim:string}>
     */
    private function mapTyres(array $combinations): array
    {
        $tyres = [];

        foreach ($combinations as $combination) {
            $axles = is_array($combination) ? ($combination['akselDekkOgFelg'] ?? []) : [];
            if (! is_array($axles)) {
                continue;
            }

            foreach ($axles as $axle) {
                $dimension = is_array($axle) ? ($axle['dekkdimensjon'] ?? null) : null;
                $parsed = is_string($dimension) ? $this->parseTyreDimension($dimension) : null;

                if ($parsed !== null) {
                    $tyres[implode(':', $parsed)] = $parsed;
                }
            }
        }

        return array_values($tyres);
    }

    /**
     * Statens exposes original rim dimension and offset, but not the complete
     * PCD/centre-bore matrix needed for automatic wheel compatibility. These
     * values are therefore informational only and are never used as a wheel filter.
     *
     * @param  array<array-key, mixed>  $combinations
     * @return list<array{dimension:string, offset:?string}>
     */
    private function mapWheels(array $combinations): array
    {
        $wheels = [];
        foreach ($combinations as $combination) {
            $axles = is_array($combination) ? ($combination['akselDekkOgFelg'] ?? []) : [];
            if (! is_array($axles)) {
                continue;
            }

            foreach ($axles as $axle) {
                if (! is_array($axle)) {
                    continue;
                }
                $dimension = $this->safeWheelToken($axle['felgdimensjon'] ?? null, 32);
                if ($dimension === null) {
                    continue;
                }
                $offset = $this->safeWheelToken($axle['innpress'] ?? null, 16);
                $key = mb_strtoupper($dimension.'|'.($offset ?? ''));
                $wheels[$key] = ['dimension' => $dimension, 'offset' => $offset];
            }
        }

        return array_values($wheels);
    }

    private function safeWheelToken(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $value = preg_replace('/\s+/', ' ', trim((string) $value));
        if ($value === null || $value === '' || mb_strlen($value) > $maxLength
            || preg_match('/^[0-9A-Z.,+\- xXJj]+$/u', $value) !== 1) {
            return null;
        }

        return $value;
    }

    /** @return array{width:string, aspect:string, rim:string}|null */
    private function parseTyreDimension(string $dimension): ?array
    {
        if (! preg_match('/(?<!\d)(?<width>\d{3})\s*\/\s*(?<aspect>\d{2,3})\s*(?:ZR|R|-)\s*(?<rim>\d{2})(?:[.,]\d+)?\b/i', $dimension, $matches)) {
            return null;
        }

        $width = (int) $matches['width'];
        $aspect = (int) $matches['aspect'];
        $rim = (int) $matches['rim'];

        if ($width < 125 || $width > 395 || $aspect < 20 || $aspect > 95 || $rim < 10 || $rim > 30) {
            return null;
        }

        return [
            'width' => (string) $width,
            'aspect' => (string) $aspect,
            'rim' => (string) $rim,
        ];
    }
}
