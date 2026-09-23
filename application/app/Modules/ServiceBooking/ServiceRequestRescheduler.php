<?php

namespace App\Modules\ServiceBooking;

use App\Models\User;
use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ServiceRequestRescheduler
{
    public function __construct(
        private readonly BookingCalendar $calendar,
        private readonly ServiceRequestChangeRecorder $changes,
    ) {}

    public function assertEligible(ServiceRequest $request): void
    {
        if (! in_array($request->status, [ServiceRequestStatus::New, ServiceRequestStatus::Contacted], true)) {
            throw ValidationException::withMessages([
                'booking_slot_key' => __('service_requests.validation.reschedule_status'),
            ]);
        }
        if (count($request->operationalVehicles()) > 2) {
            throw ValidationException::withMessages([
                'booking_slot_key' => __('service_requests.validation.reschedule_vehicle_limit'),
            ]);
        }

        $now = CarbonImmutable::now($this->calendar->timezone());
        if ($request->booking_ends_at !== null && $request->booking_ends_at->lessThanOrEqualTo($now)) {
            throw ValidationException::withMessages([
                'booking_slot_key' => __('service_requests.validation.reschedule_past'),
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    public function execute(ServiceRequest $request, User $actor, array $data): ServiceRequest
    {
        return DB::transaction(function () use ($request, $actor, $data): ServiceRequest {
            /** @var ServiceRequest $locked */
            $locked = ServiceRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $commandId = (string) $data['command_id'];
            if ($this->changes->alreadyApplied($locked, $actor, $commandId, 'reschedule')) {
                return $locked->refresh();
            }
            $this->changes->assertExpected($locked, (int) $data['expected_version']);
            $this->assertEligible($locked);

            $slotKey = (string) $data['booking_slot_key'];
            $slot = $this->calendar->claimForRequest(
                $locked,
                $slotKey,
                $this->calendar->localDate((string) $data['booking_date']),
            );
            if ($locked->booking_slot_key !== null && hash_equals($locked->booking_slot_key, $slotKey)) {
                return $this->changes->recordNoop(
                    $locked,
                    $actor,
                    $commandId,
                    'reschedule',
                    ['booking_slot_key' => ['old' => $slotKey, 'new' => $slotKey]],
                );
            }
            $localStart = $slot->startsAt->setTimezone($this->calendar->timezone());
            $localEnd = $slot->endsAt->setTimezone($this->calendar->timezone());
            $attributes = [
                'preferred_date' => $localStart->format('Y-m-d'),
                'preferred_window' => $localStart->format('H:i').'–'.$localEnd->format('H:i'),
                'preferred_period_starts_at' => null,
                'preferred_period_ends_at' => null,
                'booking_schedule_interval_id' => $slot->intervalId,
                'booking_slot_key' => $slot->key,
                'booking_starts_at' => $slot->startsAt,
                'booking_ends_at' => $slot->endsAt,
            ];

            return $this->changes->apply(
                request: $locked,
                attributes: $attributes,
                actor: $actor,
                expectedVersion: (int) $data['expected_version'],
                commandId: $commandId,
                command: 'reschedule',
            );
        }, 3);
    }
}
