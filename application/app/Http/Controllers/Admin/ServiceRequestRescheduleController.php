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
use Illuminate\Support\Facades\Validator;
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
    ): View|RedirectResponse {
        try {
            $rescheduler->assertEligible($serviceRequest);
        } catch (ValidationException $exception) {
            return $this->unavailableRedirect($input, $serviceRequest, $contexts, $exception);
        }

        $timezone = $calendar->timezone();
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $currentDate = $serviceRequest->booking_starts_at?->setTimezone($timezone)->startOfDay()
            ?? $serviceRequest->preferred_date?->toImmutable()->setTimezone($timezone)->startOfDay();
        $defaultDate = $currentDate !== null && $currentDate->greaterThanOrEqualTo($today)
            ? $currentDate
            : $today;
        $validator = Validator::make($input->query(), [
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$today->format('Y-m-d')],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);
        if ($validator->fails()) {
            return redirect()->route('admin.service-requests.reschedule.edit', array_filter([
                'serviceRequest' => $serviceRequest,
                'return_to' => $contexts->fromRequest($input),
            ]))->withErrors($validator);
        }

        $validated = $validator->validated();
        $oldBookingDate = $this->validDate(old('booking_date'), $today->format('Y-m-d'));
        $date = $calendar->localDate((string) ($validated['date'] ?? $oldBookingDate ?? $defaultDate->format('Y-m-d')));
        $availability = $calendar->availabilityForRequest($serviceRequest, $date);

        return view('admin.requests.reschedule', [
            'request' => $serviceRequest,
            'date' => $date,
            'availability' => $availability,
            'timezone' => $timezone,
            'returnTo' => $contexts->fromRequest($input),
            'commandId' => $this->validCommandId(old('command_id')),
        ]);
    }

    public function update(
        RescheduleServiceRequest $input,
        ServiceRequest $serviceRequest,
        ServiceRequestRescheduler $rescheduler,
        ServiceRequestReturnContext $contexts,
        BookingCalendar $calendar,
    ): RedirectResponse {
        try {
            $updated = $rescheduler->execute($serviceRequest, $input->user(), $input->validated());
        } catch (ValidationException $exception) {
            $serviceRequest->refresh();
            try {
                $rescheduler->assertEligible($serviceRequest);
            } catch (ValidationException $eligibilityException) {
                return $this->unavailableRedirect($input, $serviceRequest, $contexts, $eligibilityException);
            }

            $date = $this->validDate(
                $input->string('booking_date')->toString(),
                CarbonImmutable::now($calendar->timezone())->format('Y-m-d'),
            );

            return redirect()->route('admin.service-requests.reschedule.edit', array_filter([
                'serviceRequest' => $serviceRequest,
                'date' => $date,
                'return_to' => $contexts->fromRequest($input),
            ]))->withErrors($exception->errors())->withInput();
        }
        $returnTo = $contexts->fromRequest($input);

        return redirect($returnTo ?? route('admin.service-requests.show', $updated))
            ->with('status', __('service_requests.reschedule_saved'));
    }

    private function unavailableRedirect(
        Request $input,
        ServiceRequest $serviceRequest,
        ServiceRequestReturnContext $contexts,
        ValidationException $exception,
    ): RedirectResponse {
        return redirect()->route('admin.service-requests.show', array_filter([
            'request' => $serviceRequest,
            'return_to' => $contexts->fromRequest($input),
        ]))->withErrors($exception->errors());
    }

    private function validDate(mixed $candidate, string $today): ?string
    {
        if (! is_string($candidate)) {
            return null;
        }

        $validator = Validator::make(['date' => $candidate], [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
        ]);

        return $validator->fails() ? null : $candidate;
    }

    private function validCommandId(mixed $candidate): string
    {
        return is_string($candidate) && Str::isUuid($candidate)
            ? $candidate
            : (string) Str::uuid();
    }
}
