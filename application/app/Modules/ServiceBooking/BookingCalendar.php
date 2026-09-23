<?php

namespace App\Modules\ServiceBooking;

use App\Modules\ServiceBooking\Models\BookingBlackout;
use App\Modules\ServiceBooking\Models\BookingScheduleInterval;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class BookingCalendar
{
    public function timezone(): string
    {
        return (string) config('service_booking.timezone', 'Europe/Oslo');
    }

    public function localDate(string $date): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $date, $this->timezone());
    }

    public function hasConfiguredSchedule(string $location): bool
    {
        return BookingScheduleInterval::query()
            ->where('is_active', true)
            ->whereIn('location', [$location, 'both'])
            ->exists();
    }

    /**
     * @return array{configured: bool, slots: Collection<int, BookingSlot>}
     */
    public function availability(string $location, CarbonImmutable $date): array
    {
        $configured = $this->hasConfiguredSchedule($location);
        if (! $configured) {
            return ['configured' => false, 'slots' => collect()];
        }

        $intervals = BookingScheduleInterval::query()
            ->where('is_active', true)
            ->where('weekday', $date->isoWeekday())
            ->whereIn('location', [$location, 'both'])
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        return [
            'configured' => true,
            'slots' => $this->slotsForIntervals($intervals, $location, $date),
        ];
    }

    /**
     * Availability for an existing request excludes that request from the
     * capacity count. The write path rechecks everything under row locks.
     *
     * @return array{configured: bool, slots: Collection<int, BookingSlot>}
     */
    public function availabilityForRequest(ServiceRequest $request, CarbonImmutable $date): array
    {
        $location = (string) $request->fulfilment;
        $configured = $this->hasConfiguredSchedule($location);
        if (! $configured) {
            return ['configured' => false, 'slots' => collect()];
        }

        $intervals = BookingScheduleInterval::query()
            ->where('is_active', true)
            ->where('weekday', $date->isoWeekday())
            ->whereIn('location', [$location, 'both'])
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        return [
            'configured' => true,
            'slots' => $this->slotsForIntervals(
                $intervals,
                $location,
                $date,
                excludeRequestId: (int) $request->getKey(),
            ),
        ];
    }

    /**
     * Must be called inside the same database transaction that creates the
     * service request. The interval row lock serializes capacity claims.
     */
    public function claim(?string $slotKey, string $location, CarbonImmutable $date, ?string $preferredWindow): ?BookingSlot
    {
        if (! filled($slotKey)) {
            if ($this->hasConfiguredSchedule($location)) {
                throw ValidationException::withMessages([
                    'booking_slot_key' => __('booking_calendar.public.slot_required'),
                ]);
            }

            if (! filled($preferredWindow)) {
                throw ValidationException::withMessages([
                    'preferred_window' => __('booking_calendar.public.manual_window_required'),
                ]);
            }

            return null;
        }

        $parts = $this->slotKeyParts($slotKey, $date);

        /** @var BookingScheduleInterval|null $interval */
        $interval = BookingScheduleInterval::query()->whereKey((int) $parts[1])->lockForUpdate()->first();
        if ($interval === null || ! $interval->is_active || $interval->weekday !== $date->isoWeekday()) {
            throw $this->invalidSlot();
        }
        if (! in_array($interval->location, [$location, 'both'], true)) {
            throw $this->invalidSlot();
        }

        $slot = $this->slotsForIntervals(collect([$interval]), $location, $date, includeFull: true)
            ->first(fn (BookingSlot $candidate): bool => hash_equals($candidate->key, $slotKey));

        if ($slot === null) {
            throw ValidationException::withMessages([
                'booking_slot_key' => __('booking_calendar.public.slot_unavailable'),
            ]);
        }
        $slot = $this->withCurrentLockedCapacity($slot, $interval);
        if ($slot->remaining < 1) {
            throw ValidationException::withMessages([
                'booking_slot_key' => __('booking_calendar.public.slot_unavailable'),
            ]);
        }

        return $slot;
    }

    /**
     * Claim a replacement slot while holding both the old and new schedule
     * interval rows in a stable order. The existing request is excluded from
     * the target capacity count, but every other active request is retained.
     *
     * Must be called from the transaction that already locked the request.
     */
    public function claimForRequest(ServiceRequest $request, string $slotKey, CarbonImmutable $date): BookingSlot
    {
        $parts = $this->slotKeyParts($slotKey, $date);
        $targetIntervalId = (int) $parts[1];
        $intervalIds = collect([$request->booking_schedule_interval_id, $targetIntervalId])
            ->filter(fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->sort()
            ->values();
        $lockedIntervals = BookingScheduleInterval::query()
            ->whereKey($intervalIds->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        /** @var BookingScheduleInterval|null $interval */
        $interval = $lockedIntervals->get($targetIntervalId);
        $location = (string) $request->fulfilment;
        if ($interval === null || ! $interval->is_active || $interval->weekday !== $date->isoWeekday()) {
            throw $this->invalidSlot();
        }
        if (! in_array($interval->location, [$location, 'both'], true)) {
            throw $this->invalidSlot();
        }

        $slot = $this->slotsForIntervals(
            collect([$interval]),
            $location,
            $date,
            includeFull: true,
            excludeRequestId: (int) $request->getKey(),
        )->first(fn (BookingSlot $candidate): bool => hash_equals($candidate->key, $slotKey));

        if ($slot === null) {
            throw ValidationException::withMessages([
                'booking_slot_key' => __('booking_calendar.public.slot_unavailable'),
            ]);
        }
        $slot = $this->withCurrentLockedCapacity($slot, $interval, (int) $request->getKey());
        if ($slot->remaining < 1) {
            throw ValidationException::withMessages([
                'booking_slot_key' => __('booking_calendar.public.slot_unavailable'),
            ]);
        }

        return $slot;
    }

    /**
     * @param  Collection<int, BookingScheduleInterval>  $intervals
     * @return Collection<int, BookingSlot>
     */
    private function slotsForIntervals(
        Collection $intervals,
        string $location,
        CarbonImmutable $date,
        bool $includeFull = false,
        ?int $excludeRequestId = null,
    ): Collection {
        $timezone = $this->timezone();
        $day = $date->setTimezone($timezone)->startOfDay();
        $dayStartUtc = $day->utc();
        $dayEndUtc = $day->addDay()->utc();
        $now = CarbonImmutable::now($timezone);

        $blackouts = BookingBlackout::query()
            ->where('is_active', true)
            ->whereIn('location', [$location, 'both'])
            ->where('starts_at', '<', $dayEndUtc)
            ->where('ends_at', '>', $dayStartUtc)
            ->get();

        $keys = [];
        foreach ($intervals as $interval) {
            $cursor = $this->atLocalTime($day, (string) $interval->start_time);
            $intervalEnd = $this->atLocalTime($day, (string) $interval->end_time);
            if ($cursor === null || $intervalEnd === null || $cursor->offset !== $intervalEnd->offset) {
                continue;
            }
            while ($cursor->addMinutes($interval->slot_minutes)->lessThanOrEqualTo($intervalEnd)) {
                $end = $cursor->addMinutes($interval->slot_minutes);
                $key = $this->slotKey($interval->getKey(), $cursor);
                if ($cursor->greaterThan($now) && ! $this->isBlackedOut($blackouts, $cursor, $end)) {
                    $keys[$key] = [$interval, $cursor->utc(), $end->utc()];
                }
                $cursor = $end;
            }
        }

        if ($keys === []) {
            return collect();
        }

        $booked = ServiceRequest::query()
            ->selectRaw('booking_slot_key, COUNT(*) as aggregate')
            ->whereIn('booking_slot_key', array_keys($keys))
            ->where('status', '!=', 'cancelled')
            ->when($excludeRequestId !== null, fn ($query) => $query->whereKeyNot($excludeRequestId))
            ->groupBy('booking_slot_key')
            ->pluck('aggregate', 'booking_slot_key');

        return collect($keys)->map(function (array $data, string $key) use ($booked): BookingSlot {
            [$interval, $start, $end] = $data;
            $remaining = max(0, $interval->capacity - (int) ($booked[$key] ?? 0));

            return new BookingSlot($interval->getKey(), $key, $start, $end, $remaining);
        })->filter(fn (BookingSlot $slot): bool => $includeFull || $slot->remaining > 0)->values();
    }

    private function atLocalTime(CarbonImmutable $day, string $time): ?CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));
        $local = sprintf('%s %02d:%02d', $day->format('Y-m-d'), $hour, $minute);
        $wallClock = gmmktime($hour, $minute, 0, (int) $day->format('m'), (int) $day->format('d'), (int) $day->format('Y'));
        $timezone = new \DateTimeZone($this->timezone());
        $offsets = collect($timezone->getTransitions($wallClock - 86400, $wallClock + 86400))
            ->pluck('offset')
            ->unique()
            ->values();
        $matches = $offsets->map(function (int $offset) use ($wallClock, $timezone, $local): ?CarbonImmutable {
            $candidate = CarbonImmutable::createFromTimestampUTC($wallClock - $offset)->setTimezone($timezone);

            return $candidate->format('Y-m-d H:i') === $local ? $candidate : null;
        })->filter()->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function slotKey(int $intervalId, CarbonImmutable $start): string
    {
        return sprintf('b1-%d-%s-%s', $intervalId, $start->format('Ymd'), $start->format('Hi'));
    }

    /** @param Collection<int, BookingBlackout> $blackouts */
    private function isBlackedOut(Collection $blackouts, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return $blackouts->contains(fn (BookingBlackout $blackout): bool => $blackout->starts_at->lessThan($end->utc()) && $blackout->ends_at->greaterThan($start->utc())
        );
    }

    private function invalidSlot(): ValidationException
    {
        return ValidationException::withMessages([
            'booking_slot_key' => __('booking_calendar.public.slot_invalid'),
        ]);
    }

    private function withCurrentLockedCapacity(
        BookingSlot $slot,
        BookingScheduleInterval $interval,
        ?int $excludeRequestId = null,
    ): BookingSlot {
        // This is deliberately a locking read. A preceding idempotency SELECT
        // may have established a REPEATABLE READ snapshot before we waited for
        // the interval lock; a plain COUNT could then miss a concurrent claim.
        $booked = ServiceRequest::query()
            ->select('id')
            ->where('booking_slot_key', $slot->key)
            ->where('status', '!=', 'cancelled')
            ->when($excludeRequestId !== null, fn ($query) => $query->whereKeyNot($excludeRequestId))
            ->lockForUpdate()
            ->get()
            ->count();

        return new BookingSlot(
            $slot->intervalId,
            $slot->key,
            $slot->startsAt,
            $slot->endsAt,
            max(0, (int) $interval->capacity - $booked),
        );
    }

    /** @return array{0: string, 1: string, 2: string, 3: string} */
    private function slotKeyParts(string $slotKey, CarbonImmutable $date): array
    {
        if (! preg_match('/^b1-(\d+)-(\d{8})-(\d{4})$/D', $slotKey, $parts)
            || $parts[2] !== $date->format('Ymd')) {
            throw $this->invalidSlot();
        }

        return $parts;
    }
}
