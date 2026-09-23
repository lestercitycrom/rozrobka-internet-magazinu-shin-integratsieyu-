<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class RescheduleServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canOperateServiceRequests() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $bookingToday = now((string) config('service_booking.timezone', 'Europe/Oslo'))->format('Y-m-d');

        return [
            'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$bookingToday],
            'booking_slot_key' => ['required', 'string', 'max:80'],
            'expected_version' => ['required', 'integer', 'min:1'],
            'command_id' => ['required', 'uuid'],
            'confirm_reschedule' => ['accepted'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
