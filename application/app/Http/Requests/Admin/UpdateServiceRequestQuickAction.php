<?php

namespace App\Http\Requests\Admin;

use App\Modules\ServiceBooking\ServiceRequestVehiclePayload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateServiceRequestQuickAction extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canOperateServiceRequests() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $command = (string) $this->input('command');

        return [
            'command' => ['required', Rule::in(['status', 'assigned_van', 'admin_note', 'storage_number'])],
            'command_id' => ['required', 'uuid'],
            'expected_version' => ['required', 'integer', 'min:1'],
            'return_to' => ['nullable', 'string', 'max:2048'],
            'status' => [Rule::requiredIf($command === 'status'), Rule::in(['new', 'contacted', 'closed', 'cancelled'])],
            'assigned_van' => ['nullable', Rule::in(['van_1', 'van_2'])],
            'admin_note' => [Rule::requiredIf($command === 'admin_note'), 'nullable', 'string', 'max:4000'],
            'vehicle_index' => ['nullable', 'integer', 'between:0,'.(ServiceRequestVehiclePayload::MAX_VEHICLES - 1)],
            'storage_number' => ['nullable', 'string', 'max:64', 'regex:/^[\pL\pN][\pL\pN ._\/-]*$/u'],
        ];
    }
}
