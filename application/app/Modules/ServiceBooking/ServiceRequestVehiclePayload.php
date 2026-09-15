<?php

namespace App\Modules\ServiceBooking;

final class ServiceRequestVehiclePayload
{
    public const MAX_VEHICLES = 8;

    public const STORAGE_PLANS = ['none', 'new', 'renew', 'last'];

    private const LEGACY_FIELDS = [
        'vehicle', 'registration_number', 'tire_size', 'vehicle_type', 'rim_band', 'storage_plan',
        'vehicle_2', 'registration_number_2', 'tire_size_2', 'vehicle_type_2', 'rim_band_2', 'storage_plan_2',
    ];

    /** @param array<string, mixed> $input */
    public static function hasLegacyFields(array $input): bool
    {
        foreach (self::LEGACY_FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $input */
    public static function hasConflictingPayload(array $input): bool
    {
        if (! array_key_exists('vehicles', $input) || ! is_array($input['vehicles'])) {
            return false;
        }

        if (self::hasLegacyFields($input)) {
            return true;
        }

        return array_key_exists('car_count', $input)
            && (int) $input['car_count'] !== count($input['vehicles']);
    }

    /** @param array<string, mixed> $input @return list<array<string, mixed>> */
    public static function legacyVehicles(array $input): array
    {
        if (! array_key_exists('car_count', $input)) {
            return [];
        }

        $count = (int) ($input['car_count'] ?? 1);
        if ($count < 1 || $count > 2) {
            return [];
        }

        $vehicles = [self::legacyVehicle($input, '')];
        if ($count === 2) {
            $vehicles[] = self::legacyVehicle($input, '_2');
        }

        return $vehicles;
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private static function legacyVehicle(array $input, string $suffix): array
    {
        return [
            'vehicle' => $input['vehicle'.$suffix] ?? null,
            'registration_number' => $input['registration_number'.$suffix] ?? null,
            'tire_size' => $input['tire_size'.$suffix] ?? null,
            'vehicle_type' => $input['vehicle_type'.$suffix] ?? null,
            'rim_band' => $input['rim_band'.$suffix] ?? null,
            'storage_plan' => $input['storage_plan'.$suffix] ?? null,
        ];
    }

    /** @param list<array<string, mixed>> $vehicles @return list<array<string, string|null>> */
    public static function normalized(array $vehicles): array
    {
        return array_values(array_map(static function (array $vehicle): array {
            $normalized = [
                'vehicle' => self::nullableString($vehicle['vehicle'] ?? null) ?? '',
                'registration_number' => self::nullableString($vehicle['registration_number'] ?? null),
                'tire_size' => self::nullableString($vehicle['tire_size'] ?? null),
                'vehicle_type' => self::nullableString($vehicle['vehicle_type'] ?? null) ?? 'passenger',
                'rim_band' => self::nullableString($vehicle['rim_band'] ?? null) ?? 'unknown',
                'storage_plan' => self::nullableString($vehicle['storage_plan'] ?? null) ?? 'none',
            ];

            // Preserve legacy JSON shape on contact/full edits. New admin forms submit
            // this key explicitly, while historical rows that predate it stay intact.
            if (array_key_exists('storage_number', $vehicle)) {
                $normalized['storage_number'] = self::nullableString($vehicle['storage_number']);
            }

            return $normalized;
        }, $vehicles));
    }

    public static function generalPlan(bool $futureStorage, ?bool $alreadyStored): string
    {
        if ($alreadyStored === true) {
            return $futureStorage ? 'renew' : 'last';
        }

        if ($alreadyStored === false) {
            return $futureStorage ? 'new' : 'none';
        }

        return 'unknown';
    }

    /** @return array{dekkhotell: bool, dekkhotell_already_stored: bool|null} */
    public static function generalFacts(string $plan, bool $existingFutureStorage = false, ?bool $existingAlreadyStored = null): array
    {
        return match ($plan) {
            'none' => ['dekkhotell' => false, 'dekkhotell_already_stored' => false],
            'new' => ['dekkhotell' => true, 'dekkhotell_already_stored' => false],
            'renew' => ['dekkhotell' => true, 'dekkhotell_already_stored' => true],
            'last' => ['dekkhotell' => false, 'dekkhotell_already_stored' => true],
            'unknown' => ['dekkhotell' => $existingFutureStorage, 'dekkhotell_already_stored' => $existingAlreadyStored],
            default => ['dekkhotell' => false, 'dekkhotell_already_stored' => null],
        };
    }

    public static function requiresStoredSet(string $plan): bool
    {
        return in_array($plan, ['renew', 'last'], true);
    }

    public static function chargesNewStoragePeriod(string $plan): bool
    {
        return in_array($plan, ['new', 'renew'], true);
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
