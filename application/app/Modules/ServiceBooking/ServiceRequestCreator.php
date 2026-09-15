<?php

namespace App\Modules\ServiceBooking;

use App\Models\User;
use App\Modules\Outbox\OutboxPublisher;
use App\Modules\ServiceBooking\Enums\ServiceRequestSource;
use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ServiceRequestCreator
{
    public function __construct(
        private readonly BookingCalendar $calendar,
        private readonly SeasonalQuoteCalculator $quotes,
        private readonly OutboxPublisher $outbox,
        private readonly CustomerPhoneNormalizer $phones,
    ) {}

    /** @param array<string, mixed> $data */
    public function createPublic(array $data, string $slug, string $sessionFingerprint, string $locale): ServiceRequest
    {
        $data['service_slug'] = $slug;

        return $this->create(
            data: $data,
            slug: $slug,
            source: ServiceRequestSource::Public,
            actor: null,
            sessionFingerprint: $sessionFingerprint,
            locale: $locale,
            allowUnconfirmed: false,
            notify: true,
        );
    }

    /** @param array<string, mixed> $data */
    public function createManual(array $data, User $actor, string $locale): ServiceRequest
    {
        return $this->create(
            data: $data,
            slug: (string) $data['service_slug'],
            source: ServiceRequestSource::AdminManual,
            actor: $actor,
            sessionFingerprint: null,
            locale: $locale,
            allowUnconfirmed: ($data['booking_mode'] ?? 'manual') === 'manual',
            notify: false,
        );
    }

    /** @param array<string, mixed> $data */
    public function updateManual(ServiceRequest $request, array $data): ServiceRequest
    {
        return DB::transaction(function () use ($request, $data): ServiceRequest {
            /** @var ServiceRequest $locked */
            $locked = ServiceRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $contact = Arr::only($data, ['client_id', 'name', 'email', 'phone', 'address', 'district', 'assigned_van', 'note', 'admin_note', 'status']);
            if (array_key_exists('email', $contact)) {
                $contact['email'] = filled($contact['email']) ? mb_strtolower(trim((string) $contact['email'])) : null;
            }
            if (array_key_exists('phone', $contact)) {
                $contact['phone_normalized'] = $this->phones->normalize((string) $contact['phone']);
            }

            if ($locked->booking_slot_key !== null) {
                $operationalKeys = [
                    'service_slug', 'service_variant', 'fulfilment', 'booking_mode', 'preferred_date', 'preferred_window',
                    'preferred_time_start', 'preferred_time_end', 'booking_slot_key', 'car_count', 'vehicles',
                    'vehicle', 'registration_number', 'tire_size', 'wheel_count', 'storage_plan', 'dekkhotell',
                    'dekkhotell_already_stored', 'storage_number', 'additional_services',
                ];
                if (array_intersect_key($data, array_flip($operationalKeys)) !== []) {
                    $effectiveData = $this->effectiveOperationalInput($locked, $data);
                    $this->assertBookedDetailsUnchanged(
                        $locked,
                        $this->preparedOperationalData($effectiveData),
                        allowStorageNumberChanges: true,
                    );
                    $this->assertBookedControlsUnchanged($locked, $data);
                }
                if ($locked->status === ServiceRequestStatus::Cancelled
                    && ($contact['status'] ?? null) !== ServiceRequestStatus::Cancelled->value) {
                    throw ValidationException::withMessages([
                        'status' => __('service_requests.validation.booked_reactivation'),
                    ]);
                }

                $locked->update([
                    ...$contact,
                    ...$this->bookedStorageNumberChanges($locked, $data),
                ]);

                return $locked->refresh();
            }

            if (! array_key_exists('service_slug', $data)) {
                $locked->update($contact);

                return $locked->refresh();
            }

            $effectiveData = $this->effectiveOperationalInput($locked, $data);
            $operational = $this->preparedOperationalData($effectiveData);
            $this->assertExactBookingSupportsVehicleCount($effectiveData, $operational['vehicles'] ?? []);
            if (array_key_exists('vehicles', $operational)
                && $this->comparableVehicles($locked->vehicles) === $this->comparableVehicles($operational['vehicles'])) {
                // A browser form submits the newly introduced nullable storage key even
                // for historical rows. Keep the original JSON byte shape when nothing
                // operational actually changed.
                $operational['vehicles'] = $locked->vehicles;
            }
            $changed = collect($operational)->contains(
                fn (mixed $value, string $key): bool => $this->comparable($locked->getAttribute($key)) !== $this->comparable($value),
            );
            $slot = null;
            if (($effectiveData['booking_mode'] ?? 'manual') === 'exact') {
                $slot = $this->calendar->claim(
                    $effectiveData['booking_slot_key'] ?? null,
                    (string) $operational['fulfilment'],
                    $this->calendar->localDate((string) $operational['preferred_date']),
                    $operational['preferred_window'] ?? null,
                );
                $operational = $this->withSlot($operational, $slot);
                $changed = true;
            } else {
                $operational = $this->withoutSlot($operational);
            }

            if ($changed) {
                $operational = [...$operational, ...$this->quoteData($operational)];
            } else {
                $operational = Arr::except($operational, ['quote_min_minor', 'quote_max_minor', 'quote_currency']);
            }

            $locked->update([...$contact, ...$operational]);

            return $locked->refresh();
        }, 3);
    }

    /** @param array<string, mixed> $data */
    private function create(
        array $data,
        string $slug,
        ServiceRequestSource $source,
        ?User $actor,
        ?string $sessionFingerprint,
        string $locale,
        bool $allowUnconfirmed,
        bool $notify,
    ): ServiceRequest {
        $token = (string) $data['submission_token'];

        return DB::transaction(function () use ($data, $slug, $source, $actor, $sessionFingerprint, $locale, $allowUnconfirmed, $notify, $token): ServiceRequest {
            $ownsSubmission = DB::table('service_request_submissions')->insertOrIgnore([
                'token' => $token,
                'source' => $source->value,
                'actor_user_id' => $actor?->getKey(),
                'session_fingerprint' => $sessionFingerprint,
                'service_request_id' => null,
                'created_at' => now(),
            ]) === 1;

            if (! $ownsSubmission) {
                $submission = DB::table('service_request_submissions')->where('token', $token)->lockForUpdate()->first();
                $sameScope = $submission !== null
                    && hash_equals((string) $submission->source, $source->value)
                    && (int) ($submission->actor_user_id ?? 0) === (int) ($actor?->getKey() ?? 0)
                    && hash_equals((string) ($submission->session_fingerprint ?? ''), (string) ($sessionFingerprint ?? ''));
                if (! $sameScope || ! $submission?->service_request_id) {
                    throw ValidationException::withMessages([
                        'submission_token' => __('service_requests.validation.submission_conflict'),
                    ]);
                }

                return ServiceRequest::query()->findOrFail($submission->service_request_id);
            }

            $operational = $this->preparedOperationalData($data);
            $slot = null;
            $bookingMode = $data['booking_mode'] ?? null;
            $legacyExactAttempt = $bookingMode === null
                && ! $allowUnconfirmed
                && ($source !== ServiceRequestSource::Public || filled($operational['vehicles'] ?? null));
            if (count($operational['vehicles'] ?? []) > 2 && ($bookingMode === 'exact' || $legacyExactAttempt)) {
                throw ValidationException::withMessages([
                    'booking_mode' => __('service_requests.validation.exact_vehicle_limit'),
                ]);
            }
            if ($bookingMode === 'exact' || $legacyExactAttempt) {
                $slot = $this->calendar->claim(
                    $data['booking_slot_key'] ?? null,
                    (string) $operational['fulfilment'],
                    $this->calendar->localDate((string) $operational['preferred_date']),
                    $operational['preferred_window'] ?? null,
                );
                $operational = $this->withSlot($operational, $slot);
            } else {
                $operational = $this->withoutSlot($operational);
            }

            $item = ServiceRequest::create([
                ...$operational,
                ...$this->quoteData($operational),
                'reference' => $this->reference(),
                'submission_token' => $token,
                'locale' => $locale,
                'source' => $source,
                'created_by_user_id' => $actor?->getKey(),
                'client_id' => $data['client_id'] ?? null,
                'service_slug' => $slug,
                'name' => trim((string) $data['name']),
                'email' => filled($data['email'] ?? null) ? mb_strtolower(trim((string) $data['email'])) : null,
                'phone' => trim((string) $data['phone']),
                'phone_normalized' => $this->phones->normalize((string) $data['phone']),
                'address' => filled($data['address'] ?? null) ? trim((string) $data['address']) : null,
                'district' => filled($data['district'] ?? null) ? trim((string) $data['district']) : null,
                'note' => filled($data['note'] ?? null) ? trim((string) $data['note']) : null,
                'admin_note' => filled($data['admin_note'] ?? null) ? trim((string) $data['admin_note']) : null,
                'status' => ServiceRequestStatus::New,
                'consent_at' => $source === ServiceRequestSource::Public ? now() : null,
            ]);

            DB::table('service_request_submissions')->where('token', $token)->update([
                'service_request_id' => $item->getKey(),
            ]);

            if ($notify) {
                $payload = $this->notificationPayload($item);
                $this->outbox->forAggregate(
                    'service_request',
                    (string) $item->getKey(),
                    'telegram.service_request_created',
                    "telegram:service-request-created:{$item->id}",
                    $payload,
                );
            }

            return $item;
        }, 3);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function preparedOperationalData(array $data): array
    {
        $vehicles = $this->vehicles($data);
        $usesStorage = collect($vehicles)->contains(
            fn (array $vehicle): bool => ServiceRequestVehiclePayload::chargesNewStoragePeriod((string) ($vehicle['storage_plan'] ?? 'none')),
        );
        $existingFutureStorage = (bool) ($data['dekkhotell'] ?? false);
        $existingAlreadyStored = array_key_exists('dekkhotell_already_stored', $data)
            ? ($data['dekkhotell_already_stored'] === null ? null : (bool) $data['dekkhotell_already_stored'])
            : null;
        $generalPlan = (string) ($data['storage_plan'] ?? ServiceRequestVehiclePayload::generalPlan($existingFutureStorage, $existingAlreadyStored));
        $generalFacts = ServiceRequestVehiclePayload::generalFacts($generalPlan, $existingFutureStorage, $existingAlreadyStored);
        if ($vehicles === [] && (bool) ($data['legacy_storage_clear'] ?? false)) {
            $generalFacts = ['dekkhotell' => false, 'dekkhotell_already_stored' => null];
        }
        $period = $this->preferredPeriod($data);
        $primaryVehicle = $vehicles[0] ?? null;

        return [
            'service_slug' => (string) $data['service_slug'],
            'service_variant' => $data['service_variant'] ?? null,
            'fulfilment' => (string) $data['fulfilment'],
            'assigned_van' => ($data['fulfilment'] ?? null) === 'mobile' ? ($data['assigned_van'] ?? null) : null,
            'preferred_date' => $data['preferred_date'] ?? null,
            'preferred_window' => $period === null
                ? (filled($data['preferred_window'] ?? null) ? trim((string) $data['preferred_window']) : null)
                : $period['label'],
            'preferred_period_starts_at' => $period['starts_at'] ?? null,
            'preferred_period_ends_at' => $period['ends_at'] ?? null,
            'vehicle' => $primaryVehicle['vehicle'] ?? ($data['vehicle'] ?? null),
            'registration_number' => $primaryVehicle['registration_number'] ?? ($data['registration_number'] ?? null),
            'tire_size' => $primaryVehicle['tire_size'] ?? ($data['tire_size'] ?? null),
            'wheel_count' => $data['wheel_count'] ?? null,
            'dekkhotell' => $vehicles === [] ? $generalFacts['dekkhotell'] : $usesStorage,
            'dekkhotell_already_stored' => $vehicles === [] ? $generalFacts['dekkhotell_already_stored'] : null,
            'dekkhotell_number' => $vehicles === [] && filled($data['storage_number'] ?? null)
                ? trim((string) $data['storage_number'])
                : null,
            'additional_services' => $data['additional_services'] ?? null,
            'vehicles' => $vehicles === [] ? null : $vehicles,
        ];
    }

    /**
     * Preserve fields omitted by conditional/partial admin forms while still
     * treating explicitly submitted empty values as deliberate edits.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function effectiveOperationalInput(ServiceRequest $request, array $data): array
    {
        if ((string) ($data['service_slug'] ?? $request->service_slug) !== $request->service_slug) {
            return $data;
        }

        $existing = [
            'service_slug' => $request->service_slug,
            'service_variant' => $request->service_variant,
            'fulfilment' => $request->fulfilment,
            'assigned_van' => $request->assigned_van,
            'booking_mode' => $request->booking_slot_key
                ? 'exact'
                : ($request->preferred_period_starts_at && $request->preferred_period_ends_at ? 'period' : 'manual'),
            'preferred_date' => $request->preferred_date?->format('Y-m-d'),
            'preferred_window' => $request->preferred_window,
            'preferred_time_start' => $request->preferred_period_starts_at?->setTimezone($this->calendar->timezone())->format('H:i'),
            'preferred_time_end' => $request->preferred_period_ends_at?->setTimezone($this->calendar->timezone())->format('H:i'),
            'booking_slot_key' => $request->booking_slot_key,
            'vehicle' => $request->vehicle,
            'registration_number' => $request->registration_number,
            'tire_size' => $request->tire_size,
            'wheel_count' => $request->wheel_count,
            'dekkhotell_already_stored' => $request->dekkhotell_already_stored,
            'dekkhotell_number' => $request->dekkhotell_number,
        ];

        $vehicles = $request->vehicles ?? [];
        if ($vehicles !== [] && ! array_key_exists('vehicles', $data) && ! ServiceRequestVehiclePayload::hasLegacyFields($data)) {
            $existing['vehicles'] = $vehicles;
            $existing['car_count'] = count($vehicles);
        } elseif ($vehicles === []) {
            $existing['storage_plan'] = ServiceRequestVehiclePayload::generalPlan(
                (bool) $request->dekkhotell,
                $request->dekkhotell_already_stored,
            );
            $existing['storage_number'] = $request->dekkhotell_number;
        }

        foreach ($existing as $key => $value) {
            if (! array_key_exists($key, $data)) {
                $data[$key] = $value;
            }
        }

        $fullForm = (bool) ($data['operational_form'] ?? false);
        if (! array_key_exists('dekkhotell', $data)) {
            $data['dekkhotell'] = $request->dekkhotell;
        }
        if (! array_key_exists('additional_services', $data)) {
            $data['additional_services'] = $fullForm ? null : $request->additional_services;
        }

        return $data;
    }

    /** @param array<string, mixed> $operational @return array<string, mixed> */
    private function quoteData(array $operational): array
    {
        $quote = filled($operational['vehicles'] ?? null)
            ? $this->quotes->calculate((string) $operational['fulfilment'], $operational['vehicles'])
            : null;

        return [
            'quote_min_minor' => $quote['min_minor'] ?? null,
            'quote_max_minor' => $quote['max_minor'] ?? null,
            'quote_currency' => $quote['currency'] ?? null,
        ];
    }

    /** @param array<string, mixed> $data @return list<array<string, string|null>> */
    private function vehicles(array $data): array
    {
        $vehicles = is_array($data['vehicles'] ?? null)
            ? array_values($data['vehicles'])
            : ServiceRequestVehiclePayload::legacyVehicles($data);

        return ServiceRequestVehiclePayload::normalized($vehicles);
    }

    /** @param array<string, mixed> $data @param list<array<string, mixed>> $vehicles */
    private function assertExactBookingSupportsVehicleCount(array $data, array $vehicles): void
    {
        if (($data['booking_mode'] ?? null) === 'exact' && count($vehicles) > 2) {
            throw ValidationException::withMessages([
                'booking_mode' => __('service_requests.validation.exact_vehicle_limit'),
            ]);
        }
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function withSlot(array $data, ?BookingSlot $slot): array
    {
        if ($slot === null) {
            return $this->withoutSlot($data);
        }

        $data['preferred_window'] = $slot->startsAt->setTimezone($this->calendar->timezone())->format('H:i')
            .'–'.$slot->endsAt->setTimezone($this->calendar->timezone())->format('H:i');
        $data['booking_schedule_interval_id'] = $slot->intervalId;
        $data['booking_slot_key'] = $slot->key;
        $data['booking_starts_at'] = $slot->startsAt;
        $data['booking_ends_at'] = $slot->endsAt;
        $data['preferred_period_starts_at'] = null;
        $data['preferred_period_ends_at'] = null;

        return $data;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function withoutSlot(array $data): array
    {
        return [...$data, 'booking_schedule_interval_id' => null, 'booking_slot_key' => null, 'booking_starts_at' => null, 'booking_ends_at' => null];
    }

    /** @param array<string, mixed> $data @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, label: string}|null */
    private function preferredPeriod(array $data): ?array
    {
        if (($data['booking_mode'] ?? null) !== 'period') {
            return null;
        }

        $date = (string) ($data['preferred_date'] ?? '');
        $start = (string) ($data['preferred_time_start'] ?? '');
        $end = (string) ($data['preferred_time_end'] ?? '');
        if ($date === '' || $start === '' || $end === '') {
            $messages = [];
            if ($date === '') {
                $messages['preferred_date'] = __('service_requests.validation.preferred_date_required');
            }
            if ($start === '') {
                $messages['preferred_time_start'] = __('service_requests.validation.period_start_required');
            }
            if ($end === '') {
                $messages['preferred_time_end'] = __('service_requests.validation.period_end_required');
            }
            throw ValidationException::withMessages($messages);
        }
        $timezone = $this->calendar->timezone();

        return [
            'starts_at' => CarbonImmutable::createFromFormat('Y-m-d H:i', "{$date} {$start}", $timezone)->utc(),
            'ends_at' => CarbonImmutable::createFromFormat('Y-m-d H:i', "{$date} {$end}", $timezone)->utc(),
            'label' => "{$start}–{$end}",
        ];
    }

    /** @param array<string, mixed> $data */
    private function assertBookedDetailsUnchanged(
        ServiceRequest $request,
        array $data,
        bool $allowStorageNumberChanges = false,
    ): void {
        $canonicalVehiclesAreAuthoritative = $allowStorageNumberChanges
            && is_array($request->vehicles)
            && $request->vehicles !== []
            && array_key_exists('vehicles', $data)
            && $this->comparableVehicles($request->vehicles, true)
                === $this->comparableVehicles($data['vehicles'], true);
        $redundantVehicleMirrors = [
            'vehicle', 'registration_number', 'tire_size', 'dekkhotell',
            'dekkhotell_already_stored', 'dekkhotell_number',
        ];
        $protected = [
            'service_slug', 'service_variant', 'fulfilment', 'preferred_date', 'preferred_window',
            'preferred_period_starts_at', 'preferred_period_ends_at', 'booking_slot_key', 'vehicles',
            'vehicle', 'registration_number', 'tire_size', 'wheel_count', 'dekkhotell',
            'dekkhotell_already_stored', 'dekkhotell_number', 'additional_services',
        ];
        foreach ($protected as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            if ($allowStorageNumberChanges
                && ($key === 'dekkhotell_number'
                    || ($canonicalVehiclesAreAuthoritative && in_array($key, $redundantVehicleMirrors, true)))) {
                continue;
            }
            $matches = $key === 'vehicles'
                ? $this->comparableVehicles($request->getAttribute($key), $allowStorageNumberChanges)
                    === $this->comparableVehicles($data[$key], $allowStorageNumberChanges)
                : $this->comparable($request->getAttribute($key)) === $this->comparable($data[$key]);
            if (! $matches) {
                throw ValidationException::withMessages([
                    $key => __('service_requests.validation.booked_details_locked'),
                ]);
            }
        }
    }

    /** @param array<string, mixed> $submitted */
    private function assertBookedControlsUnchanged(ServiceRequest $request, array $submitted): void
    {
        $expected = [
            'booking_mode' => 'exact',
            'booking_slot_key' => $request->booking_slot_key,
            'preferred_time_start' => null,
            'preferred_time_end' => null,
            'car_count' => is_array($request->vehicles) ? count($request->vehicles) : null,
        ];

        foreach ($expected as $key => $value) {
            if (! array_key_exists($key, $submitted)) {
                continue;
            }
            if ($this->comparable($submitted[$key]) !== $this->comparable($value)) {
                throw ValidationException::withMessages([
                    $key => __('service_requests.validation.booked_details_locked'),
                ]);
            }
        }
    }

    /** @param array<string, mixed> $submitted @return array<string, mixed> */
    private function bookedStorageNumberChanges(ServiceRequest $request, array $submitted): array
    {
        $storedVehicles = is_array($request->vehicles) ? array_values($request->vehicles) : [];
        if ($storedVehicles !== []) {
            if (! is_array($submitted['vehicles'] ?? null)) {
                return [];
            }

            $vehicles = $storedVehicles;
            foreach (array_values($submitted['vehicles']) as $index => $vehicle) {
                if (! is_array($vehicle) || ! array_key_exists('storage_number', $vehicle)) {
                    continue;
                }
                if (! in_array($vehicles[$index]['storage_plan'] ?? 'none', ['new', 'renew', 'last'], true)) {
                    throw ValidationException::withMessages([
                        "vehicles.{$index}.storage_number" => __('service_requests.validation.storage_number_requires_active_plan'),
                    ]);
                }
                $number = $this->storageNumber($vehicle['storage_number']);
                $storedNumber = $this->storageNumber($vehicles[$index]['storage_number'] ?? null);
                if ($number === $storedNumber) {
                    continue;
                }
                $vehicles[$index]['storage_number'] = $number;
            }

            return $vehicles === $storedVehicles ? [] : ['vehicles' => $vehicles];
        }

        if (! array_key_exists('storage_number', $submitted)) {
            return [];
        }

        $number = $this->storageNumber($submitted['storage_number']);

        return $number === $request->dekkhotell_number ? [] : ['dekkhotell_number' => $number];
    }

    private function storageNumber(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function comparable(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_array($value)) {
            return json_encode($this->canonical($value), JSON_THROW_ON_ERROR);
        }

        return (string) ($value ?? '');
    }

    private function comparableVehicles(mixed $vehicles, bool $ignoreStorageNumbers = false): string
    {
        if (! is_array($vehicles)) {
            return $this->comparable($vehicles);
        }

        $normalized = ServiceRequestVehiclePayload::normalized($vehicles);
        foreach ($normalized as &$vehicle) {
            if ($ignoreStorageNumbers) {
                unset($vehicle['storage_number']);
            } else {
                $vehicle['storage_number'] = $vehicle['storage_number'] ?? null;
            }
        }
        unset($vehicle);

        return $this->comparable($normalized);
    }

    private function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonical($item);
            }
        }

        return $value;
    }

    private function reference(): string
    {
        do {
            $reference = 'SR-'.Str::upper(Str::random(14));
        } while (ServiceRequest::query()->where('reference', $reference)->exists());

        return $reference;
    }

    /** @return array<string, mixed> */
    private function notificationPayload(ServiceRequest $item): array
    {
        return [
            'event' => 'service_request_created',
            'reference' => $item->reference,
            'service_slug' => $item->service_slug,
            'fulfilment' => $item->fulfilment,
            'preferred_date' => $item->preferred_date?->format('Y-m-d'),
            'booking_starts_at' => $item->booking_starts_at?->toIso8601String(),
            'booking_ends_at' => $item->booking_ends_at?->toIso8601String(),
            'car_count' => max(1, count($item->vehicles ?? [])),
            'dekkhotell' => $item->dekkhotell,
            'quote_min_minor' => $item->quote_min_minor,
            'quote_max_minor' => $item->quote_max_minor,
            'currency' => $item->quote_currency,
        ];
    }
}
