<?php

namespace App\Http\Requests\Admin;

use App\Rules\CustomerPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpsertClient extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canOperateServiceRequests() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:24', new CustomerPhone],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:4000'],
            'link_request_id' => ['nullable', 'integer', Rule::exists('service_requests', 'id')],
            'expected_version' => [Rule::requiredIf($this->filled('link_request_id')), 'nullable', 'integer', 'min:1'],
            'resolve_conflict' => ['sometimes', 'accepted'],
            'conflict_version' => [Rule::requiredIf($this->boolean('resolve_conflict')), 'nullable', 'integer', 'min:1'],
            'command_id' => [Rule::requiredIf($this->filled('link_request_id')), 'nullable', 'uuid'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
