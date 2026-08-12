<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\ServiceBooking\Models\BookingBlackout;
use App\Modules\ServiceBooking\Models\BookingScheduleInterval;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class BookingCalendarController extends Controller
{
    public function index(): View
    {
        return view('admin.booking-calendar.index', [
            'intervals' => BookingScheduleInterval::query()->orderBy('weekday')->orderBy('start_time')->get(),
            'blackouts' => BookingBlackout::query()->orderByDesc('starts_at')->paginate(25),
            'timezone' => $this->timezone(),
        ]);
    }

    public function createInterval(): View
    {
        return view('admin.booking-calendar.interval-form', ['interval' => new BookingScheduleInterval]);
    }

    public function storeInterval(Request $request): RedirectResponse
    {
        $interval = BookingScheduleInterval::create($this->intervalData($request));

        return redirect()->route('admin.booking-calendar.intervals.edit', $interval)
            ->with('status', __('booking_calendar.admin.interval_saved'));
    }

    public function editInterval(BookingScheduleInterval $interval): View
    {
        return view('admin.booking-calendar.interval-form', compact('interval'));
    }

    public function updateInterval(Request $request, BookingScheduleInterval $interval): RedirectResponse
    {
        $interval->update($this->intervalData($request, $interval));

        return redirect()->route('admin.booking-calendar.intervals.edit', $interval)
            ->with('status', __('booking_calendar.admin.interval_saved'));
    }

    public function destroyInterval(BookingScheduleInterval $interval): RedirectResponse
    {
        $interval->delete();

        return redirect()->route('admin.booking-calendar.index')
            ->with('status', __('booking_calendar.admin.interval_deleted'));
    }

    public function createBlackout(): View
    {
        return view('admin.booking-calendar.blackout-form', [
            'blackout' => new BookingBlackout,
            'timezone' => $this->timezone(),
        ]);
    }

    public function storeBlackout(Request $request): RedirectResponse
    {
        $blackout = BookingBlackout::create($this->blackoutData($request));

        return redirect()->route('admin.booking-calendar.blackouts.edit', $blackout)
            ->with('status', __('booking_calendar.admin.blackout_saved'));
    }

    public function editBlackout(BookingBlackout $blackout): View
    {
        return view('admin.booking-calendar.blackout-form', [
            'blackout' => $blackout,
            'timezone' => $this->timezone(),
        ]);
    }

    public function updateBlackout(Request $request, BookingBlackout $blackout): RedirectResponse
    {
        $blackout->update($this->blackoutData($request));

        return redirect()->route('admin.booking-calendar.blackouts.edit', $blackout)
            ->with('status', __('booking_calendar.admin.blackout_saved'));
    }

    public function destroyBlackout(BookingBlackout $blackout): RedirectResponse
    {
        $blackout->delete();

        return redirect()->route('admin.booking-calendar.index')
            ->with('status', __('booking_calendar.admin.blackout_deleted'));
    }

    /** @return array<string, mixed> */
    private function intervalData(Request $request, ?BookingScheduleInterval $current = null): array
    {
        $data = $request->validate([
            'weekday' => ['required', 'integer', 'between:1,7'],
            'location' => ['required', Rule::in(['workshop', 'mobile', 'both'])],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_minutes' => ['required', 'integer', 'between:5,480'],
            'capacity' => ['required', 'integer', 'between:1,100'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        $start = CarbonImmutable::createFromFormat('!H:i', $data['start_time']);
        $end = CarbonImmutable::createFromFormat('!H:i', $data['end_time']);
        if ($start->diffInMinutes($end) < (int) $data['slot_minutes']) {
            throw ValidationException::withMessages([
                'slot_minutes' => __('booking_calendar.admin.slot_longer_than_interval'),
            ]);
        }

        if ($data['is_active']) {
            $locations = $data['location'] === 'both' ? ['workshop', 'mobile', 'both'] : [$data['location'], 'both'];
            $overlap = BookingScheduleInterval::query()
                ->when($current?->exists, fn ($query) => $query->whereKeyNot($current->getKey()))
                ->where('is_active', true)
                ->where('weekday', $data['weekday'])
                ->whereIn('location', $locations)
                ->where('start_time', '<', $data['end_time'])
                ->where('end_time', '>', $data['start_time'])
                ->exists();
            if ($overlap) {
                throw ValidationException::withMessages([
                    'start_time' => __('booking_calendar.admin.interval_overlap'),
                ]);
            }
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function blackoutData(Request $request): array
    {
        $data = $request->validate([
            'location' => ['required', Rule::in(['workshop', 'mobile', 'both'])],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['starts_at'] = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['starts_at'], $this->timezone())->utc();
        $data['ends_at'] = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['ends_at'], $this->timezone())->utc();
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function timezone(): string
    {
        return (string) config('service_booking.timezone', 'Europe/Oslo');
    }
}
