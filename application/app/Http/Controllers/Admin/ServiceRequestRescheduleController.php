<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RescheduleServiceRequest;
use App\Modules\ServiceBooking\BookingCalendar;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use App\Modules\ServiceBooking\ServiceRequestRescheduler;
use App\Modules\ServiceBooking\ServiceRequestReturnContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class ServiceRequestRescheduleController extends Controller
{
    public function edit(
        Request $input,
        ServiceRequest $serviceRequest,
        BookingCalendar $calendar,
        ServiceRequestRescheduler $rescheduler,
        ServiceRequestReturnContext $contexts,
    ): View {
        $rescheduler->assertEligible($serviceRequest);
        $timezone = $calendar->timezone();
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $currentDate = $serviceRequest->booking_starts_at?->setTimezone($timezone)->startOfDay()
            ?? $serviceRequest->preferred_date?->toImmutable()->setTimezone($timezone)->startOfDay();
        $defaultDate = $currentDate !== null && $currentDate->greaterThanOrEqualTo($today)
            ? $currentDate
            : $today;
        $validated = $input->validate([
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$today->format('Y-m-d')],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);
        $date = $calendar->localDate((string) ($validated['date'] ?? old('booking_date', $defaultDate->format('Y-m-d'))));
        $availability = $calendar->availabilityForRequest($serviceRequest, $date);

        return view('admin.requests.reschedule', [
            'request' => $serviceRequest,
            'date' => $date,
            'availability' => $availability,
            'timezone' => $timezone,
            'returnTo' => $contexts->fromRequest($input),
            'commandId' => old('command_id', (string) Str::uuid()),
        ]);
    }

    public function update(
        RescheduleServiceRequest $input,
        ServiceRequest $serviceRequest,
        ServiceRequestRescheduler $rescheduler,
        ServiceRequestReturnContext $contexts,
    ): RedirectResponse {
        try {
            $updated = $rescheduler->execute($serviceRequest, $input->user(), $input->validated());
        } catch (ValidationException $exception) {
            throw $exception->redirectTo(route('admin.service-requests.reschedule.edit', [
                'serviceRequest' => $serviceRequest,
                'date' => $input->string('booking_date')->toString(),
                'return_to' => $contexts->fromRequest($input),
            ]));
        }
        $returnTo = $contexts->fromRequest($input);

        return redirect($returnTo ?? route('admin.service-requests.show', $updated))
            ->with('status', __('service_requests.reschedule_saved'));
    }
}
