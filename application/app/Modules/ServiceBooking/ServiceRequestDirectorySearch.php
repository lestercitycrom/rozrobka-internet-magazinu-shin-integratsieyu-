<?php

namespace App\Modules\ServiceBooking;

use App\Modules\ServiceBooking\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Builder;

final class ServiceRequestDirectorySearch
{
    public function __construct(private readonly CustomerPhoneNormalizer $phones) {}

    /** @param Builder<ServiceRequest> $query */
    public function apply(Builder $query, ?string $search, ?string $storageOperation): Builder
    {
        $driver = $query->getModel()->getConnection()->getDriverName();
        if ($storageOperation !== null) {
            $this->whereStorageOperation($query, $storageOperation, $driver);
        }

        $needle = trim((string) $search);
        if ($needle === '') {
            return $query;
        }

        $pattern = $this->likePattern($needle);
        $compactPattern = $this->likePattern((string) preg_replace('/\s+/u', '', $needle));
        $phone = preg_match('/^[+\d\s().-]+$/u', $needle) === 1
            ? $this->phones->searchTerm($needle)
            : null;
        $phonePattern = $phone === null ? null : $this->likePattern($phone);

        return $query->where(function (Builder $matches) use ($driver, $pattern, $compactPattern, $phonePattern, $storageOperation): void {
            $matches
                ->whereRaw("LOWER(service_requests.reference) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                ->orWhereRaw("LOWER(service_requests.name) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                ->orWhereRaw("LOWER(COALESCE(service_requests.email, '')) LIKE LOWER(?) ESCAPE '!'", [$pattern]);

            if ($phonePattern !== null) {
                $matches->orWhereRaw("COALESCE(service_requests.phone_normalized, '') LIKE ? ESCAPE '!'", [$phonePattern]);
            }

            $matches->orWhere(function (Builder $general) use ($driver, $compactPattern, $pattern, $storageOperation): void {
                $this->whereGeneralRecord($general, $driver);
                $general->where(function (Builder $value) use ($compactPattern, $pattern): void {
                    $value->whereRaw("REPLACE(UPPER(COALESCE(service_requests.registration_number, '')), ' ', '') LIKE UPPER(?) ESCAPE '!'", [$compactPattern])
                        ->orWhereRaw("LOWER(COALESCE(service_requests.dekkhotell_number, '')) LIKE LOWER(?) ESCAPE '!'", [$pattern]);
                });
                if ($storageOperation !== null) {
                    $this->whereGeneralOperation($general, $storageOperation);
                }
            });

            if ($driver === 'sqlite') {
                $sql = "json_valid(service_requests.vehicles) = 1 AND EXISTS (SELECT 1 FROM json_each(service_requests.vehicles) AS vehicle WHERE (REPLACE(UPPER(COALESCE(json_extract(vehicle.value, '$.registration_number'), '')), ' ', '') LIKE UPPER(?) ESCAPE '!' OR LOWER(COALESCE(json_extract(vehicle.value, '$.storage_number'), '')) LIKE LOWER(?) ESCAPE '!')";
                $bindings = [$compactPattern, $pattern];
                if ($storageOperation !== null) {
                    $sql .= " AND LOWER(COALESCE(json_extract(vehicle.value, '$.storage_plan'), 'unknown')) = ?";
                    $bindings[] = $storageOperation;
                }
                $matches->orWhereRaw($sql.')', $bindings);

                return;
            }

            $operationSql = $storageOperation === null
                ? ''
                : " AND LOWER(COALESCE(vehicle_lookup.storage_plan, 'unknown')) = ?";
            $bindings = [$compactPattern, $pattern];
            if ($storageOperation !== null) {
                $bindings[] = $storageOperation;
            }
            $matches->orWhereRaw(
                "EXISTS (SELECT 1 FROM JSON_TABLE(COALESCE(service_requests.vehicles, JSON_ARRAY()), '$[*]' COLUMNS (registration_number VARCHAR(255) PATH '$.registration_number' NULL ON EMPTY NULL ON ERROR, storage_number VARCHAR(255) PATH '$.storage_number' NULL ON EMPTY NULL ON ERROR, storage_plan VARCHAR(32) PATH '$.storage_plan' NULL ON EMPTY NULL ON ERROR)) AS vehicle_lookup WHERE (REPLACE(UPPER(CONVERT(COALESCE(vehicle_lookup.registration_number, '') USING utf8mb4)), ' ', '') LIKE UPPER(CONVERT(? USING utf8mb4)) ESCAPE '!' OR LOWER(CONVERT(COALESCE(vehicle_lookup.storage_number, '') USING utf8mb4)) LIKE LOWER(CONVERT(? USING utf8mb4)) ESCAPE '!'){$operationSql})",
                $bindings,
            );
        });
    }

    public function matchReason(ServiceRequest $request, string $search, ?string $storageOperation = null): ?array
    {
        $needle = trim($search);
        if ($needle === '') {
            return null;
        }
        $contains = static fn (?string $value): bool => $value !== null
            && mb_stripos($value, $needle) !== false;
        $compactNeedle = preg_replace('/\s+/u', '', mb_strtoupper($needle));
        $registrationContains = static fn (?string $value): bool => $value !== null
            && str_contains((string) preg_replace('/\s+/u', '', mb_strtoupper($value)), (string) $compactNeedle);

        $phoneNeedle = preg_match('/^[+\d\s().-]+$/u', $needle) === 1
            ? $this->phones->searchTerm($needle)
            : null;

        foreach ([
            'reference' => $request->reference,
            'name' => $request->name,
            'email' => $request->email,
        ] as $field => $value) {
            if ($contains($value)) {
                return ['field' => $field, 'value' => $value];
            }
        }

        if ($phoneNeedle !== null
            && filled($request->phone_normalized)
            && str_contains((string) $request->phone_normalized, $phoneNeedle)) {
            return ['field' => 'phone', 'value' => $request->phone];
        }

        foreach ($request->operationalVehicles() as $index => $vehicle) {
            if ($storageOperation !== null && ($vehicle['storage_plan'] ?? 'unknown') !== $storageOperation) {
                continue;
            }
            if ($registrationContains($vehicle['registration_number'] ?? null)) {
                return ['field' => 'registration_number', 'value' => $vehicle['registration_number'], 'vehicle' => $index + 1];
            }
            if ($contains($vehicle['storage_number'] ?? null)) {
                return ['field' => 'storage_number', 'value' => $vehicle['storage_number'], 'vehicle' => $index + 1];
            }
        }

        return null;
    }

    /** @param Builder<ServiceRequest> $query */
    public function whereStorageOperation(Builder $query, string $operation, ?string $driver = null): void
    {
        $driver ??= $query->getModel()->getConnection()->getDriverName();
        $query->where(function (Builder $storage) use ($driver, $operation): void {
            $storage->where(function (Builder $general) use ($driver, $operation): void {
                $this->whereGeneralRecord($general, $driver);
                $this->whereGeneralOperation($general, $operation);
            });

            if ($driver === 'sqlite') {
                $storage->orWhereRaw(
                    "json_valid(service_requests.vehicles) = 1 AND EXISTS (SELECT 1 FROM json_each(service_requests.vehicles) AS vehicle WHERE LOWER(COALESCE(json_extract(vehicle.value, '$.storage_plan'), 'unknown')) = ?)",
                    [$operation],
                );

                return;
            }

            $storage->orWhereRaw(
                "EXISTS (SELECT 1 FROM JSON_TABLE(COALESCE(service_requests.vehicles, JSON_ARRAY()), '$[*]' COLUMNS (storage_plan VARCHAR(32) PATH '$.storage_plan' NULL ON EMPTY NULL ON ERROR)) AS vehicle_storage WHERE LOWER(COALESCE(vehicle_storage.storage_plan, 'unknown')) = ?)",
                [$operation],
            );
        });
    }

    /** @param Builder<ServiceRequest> $query */
    public function whereMissingStoredKit(Builder $query): void
    {
        $driver = $query->getModel()->getConnection()->getDriverName();
        $query->where(function (Builder $missing) use ($driver): void {
            $missing->where(function (Builder $general) use ($driver): void {
                $this->whereGeneralRecord($general, $driver);
                $general->where('service_requests.dekkhotell_already_stored', true)
                    ->where(function (Builder $number): void {
                        $number->whereNull('service_requests.dekkhotell_number')
                            ->orWhere('service_requests.dekkhotell_number', '');
                    });
            });

            if ($driver === 'sqlite') {
                $missing->orWhereRaw(
                    "json_valid(service_requests.vehicles) = 1 AND EXISTS (SELECT 1 FROM json_each(service_requests.vehicles) AS vehicle WHERE LOWER(COALESCE(json_extract(vehicle.value, '$.storage_plan'), 'unknown')) IN ('renew', 'last') AND TRIM(COALESCE(json_extract(vehicle.value, '$.storage_number'), '')) = '')",
                );

                return;
            }

            $missing->orWhereRaw(
                "EXISTS (SELECT 1 FROM JSON_TABLE(COALESCE(service_requests.vehicles, JSON_ARRAY()), '$[*]' COLUMNS (storage_plan VARCHAR(32) PATH '$.storage_plan' NULL ON EMPTY NULL ON ERROR, storage_number VARCHAR(255) PATH '$.storage_number' NULL ON EMPTY NULL ON ERROR)) AS vehicle_missing WHERE LOWER(COALESCE(vehicle_missing.storage_plan, 'unknown')) IN ('renew', 'last') AND TRIM(COALESCE(vehicle_missing.storage_number, '')) = '')",
            );
        });
    }

    /** @param Builder<ServiceRequest> $query */
    private function whereGeneralRecord(Builder $query, string $driver): void
    {
        $query->where(function (Builder $general) use ($driver): void {
            $general->whereNull('service_requests.vehicles');
            if ($driver === 'sqlite') {
                $general->orWhereRaw('json_valid(service_requests.vehicles) = 1 AND json_array_length(service_requests.vehicles) = 0');
            } else {
                $general->orWhereRaw('JSON_LENGTH(service_requests.vehicles) = 0');
            }
        });
    }

    /** @param Builder<ServiceRequest> $query */
    private function whereGeneralOperation(Builder $query, string $operation): void
    {
        match ($operation) {
            'new' => $query->where('service_requests.dekkhotell', true)->where('service_requests.dekkhotell_already_stored', false),
            'renew' => $query->where('service_requests.dekkhotell', true)->where('service_requests.dekkhotell_already_stored', true),
            'last' => $query->where('service_requests.dekkhotell', false)->where('service_requests.dekkhotell_already_stored', true),
            'none' => $query->where('service_requests.dekkhotell', false)->where('service_requests.dekkhotell_already_stored', false),
            default => $query->whereNull('service_requests.dekkhotell_already_stored'),
        };
    }

    private function likePattern(string $value): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%';
    }
}
