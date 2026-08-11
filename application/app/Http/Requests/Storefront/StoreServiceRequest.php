<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
{
    public function rules(): array
    {
        $slug = mb_strtolower((string) $this->route('slug'));
        $requiresProblem = in_array($slug, ['tpms-sensor', 'bilbatteri', 'bildiagnose', 'lyspaerer-viskerblad'], true);

        return [
            'service_variant' => [Rule::requiredIf($slug === 'omlegging-balansering'), 'nullable', 'string', Rule::in(['mounting_balancing', 'balancing_only'])],
            'fulfilment' => ['required', 'in:workshop,mobile'],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
            'preferred_window' => ['nullable', 'string', 'max:64'],
            'vehicle' => ['nullable', 'string', 'max:120'],
            'registration_number' => ['nullable', 'string', 'max:32'],
            'tire_size' => ['nullable', 'string', 'max:64'],
            'wheel_count' => [Rule::requiredIf($slug === 'omlegging-balansering'), 'nullable', 'integer', 'between:1,4'],
            'dekkhotell' => ['nullable', 'boolean'],
            'additional_services' => ['nullable', 'array', 'max:3'],
            'additional_services.*' => ['string', 'distinct', Rule::in(['air_filter', 'cabin_filter', 'wiper_blades'])],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:64'],
            'address' => ['nullable', 'required_if:fulfilment,mobile', 'string', 'max:255'],
            'note' => [$requiresProblem ? 'required' : 'nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
            'website' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.required' => __('project.request.problem_required'),
        ];
    }
}
