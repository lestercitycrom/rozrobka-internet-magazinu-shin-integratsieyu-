<?php

namespace App\Modules\ServiceBooking;

use App\Models\User;
use App\Modules\Outbox\OutboxPublisher;
use App\Modules\ServiceBooking\Enums\ServiceRequestSource;
use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use App\Modules\ServiceBooking\Models\ServiceRequest;
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
            $contact = Arr::only($data, ['name', 'email', 'phone', 'address', 'note', 'admin_note', 'status']);
            if (array_key_exists('email', $contact)) {
                $contact['email'] = filled($contact['email']) ? mb_strtolower(trim((string) $contact['email'])) : null;
            }
            if (array_key_exists('phone', $contact)) {
                $contact['phone_normalized'] = $this->phones->normalize((string) $contact['phone']);
            }

            if ($locked->booking_slot_key !== null) {
                $this->assertBookedDetailsUnchanged($locked, $data);
                if ($locked->status === ServiceRequestStatus::Cancelled
                    && ($contact['status'] ?? null) !== ServiceRequestStatus::Cancelled->value) {
                    throw ValidationException::withMessages([
                        'status' => __('service_requests.validation.booked_reactivation'),
                    ]);
                }

                $locked->update($contact);

                return $locked->refresh();
            }

            if (! array_key_exists('service_slug', $data)) {
                $locked->update($contact);

                return $locked->refresh();
            }

            $operational = $this->preparedOperationalData($data);
            $changed = collect($operational)->contains(
                fn (mixed $value, string $key): bool => $this->comparable($locked->getAttribute($key)) !== $this->comparable($value),
            );
            $slot = null;
            if (($data['booking_mode'] ?? 'manual') === 'exact') {
                $slot = $this->calendar->claim(
                    $data['booking_slot_key'] ?? null,
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
            if (! $allowUnconfirmed && ($source !== ServiceRequestSource::Public || filled($operational['vehicles'] ?? null))) {
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
                'service_slug' => $slug,
                'name' => trim((string) $data['name']),
                'email' => filled($data['email'] ?? null) ? mb_strtolower(trim((string) $data['email'])) : null,
                'phone' => trim((string) $data['phone']),
                'phone_normalized' => $this->phones->normalize((string) $data['phone']),
                'address' => filled($data['address'] ?? null) ? trim((string) $data['address']) : null,
                'note' => filled($data['note'] ?? null) ? trim((string) $data['note']) : null,
                'admin_note' => filled($data['admin_note'] ?? null) ? trim((string) $data['admin_note']) : null,
                'status' => $data['status'] ?? ServiceRequestStatus::New,
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
        $usesStorage = collect($vehicles)->contains(fn (array $vehicle): bool => ($vehicle['storage_plan'] ?? 'none') !== 'none');

        return [
            'service_slug' => (string) $data['service_slug'],
            'service_variant' => $data['service_variant'] ?? null,
            'fulfilment' => (string) $data['fulfilment'],
            'preferred_date' => $data['preferred_date'] ?? null,
            'preferred_window' => filled($data['preferred_window'] ?? null) ? trim((string) $data['preferred_window']) : null,
            'vehicle' => $data['vehicle'] ?? null,
            'registration_number' => $data['registration_number'] ?? null,
            'tire_size' => $data['tire_size'] ?? null,
            'wheel_count' => $data['wheel_count'] ?? null,
            'dekkhotell' => $vehicles === [] ? (bool) ($data['dekkhotell'] ?? false) : $usesStorage,
            'additional_services' => $data['additional_services'] ?? null,
            'vehicles' => $vehicles === [] ? null : $vehicles,
        ];
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
        if (! isset($data['car_count'])) {
            return [];
        }

        $vehicles = [[
            'vehicle' => (string) ($data['vehicle'] ?? ''),
            'registration_number' => $data['registration_number'] ?? null,
            'tire_size' => $data['tire_size'] ?? null,
            'vehicle_type' => (string) ($data['vehicle_type'] ?? 'passenger'),
            'rim_band' => (string) ($data['rim_band'] ?? 'unknown'),
            'storage_plan' => (string) ($data['storage_plan'] ?? 'none'),
        ]];

        if ((int) $data['car_count'] === 2) {
            $vehicles[] = [
                'vehicle' => (string) ($data['vehicle_2'] ?? ''),
                'registration_number' => $data['registration_number_2'] ?? null,
                'tire_size' => $data['tire_size_2'] ?? null,
                'vehicle_type' => (string) ($data['vehicle_type_2'] ?? 'passenger'),
                'rim_band' => (string) ($data['rim_band_2'] ?? 'unknown'),
                'storage_plan' => (string) ($data['storage_plan_2'] ?? 'none'),
            ];
        }

        return $vehicles;
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

        return $data;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function withoutSlot(array $data): array
    {
        return [...$data, 'booking_schedule_interval_id' => null, 'booking_slot_key' => null, 'booking_starts_at' => null, 'booking_ends_at' => null];
    }

    /** @param array<string, mixed> $data */
    private function assertBookedDetailsUnchanged(ServiceRequest $request, array $data): void
    {
        $protected = [
            'service_slug', 'service_variant', 'fulfilment', 'preferred_date', 'preferred_window',
            'booking_slot_key', 'vehicle', 'registration_number', 'tire_size', 'wheel_count',
            'dekkhotell', 'additional_services',
        ];
        foreach ($protected as $key) {
            if (array_key_exists($key, $data)
                && $this->comparable($request->getAttribute($key)) !== $this->comparable($data[$key])) {
                throw ValidationException::withMessages([
                    $key => __('service_requests.validation.booked_details_locked'),
                ]);
            }
        }
    }

    private function comparable(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return (string) ($value ?? '');
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
