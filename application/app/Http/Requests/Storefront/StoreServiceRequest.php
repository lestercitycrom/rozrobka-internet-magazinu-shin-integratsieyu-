<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'fulfilment' => ['required', 'in:workshop,mobile'],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
            'preferred_window' => ['nullable', 'string', 'max:64'],
            'vehicle' => ['nullable', 'string', 'max:120'],
            'registration_number' => ['nullable', 'string', 'max:32'],
            'tire_size' => ['nullable', 'string', 'max:64'],
            'dekkhotell' => ['nullable', 'boolean'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:64'],
            'address' => ['nullable', 'required_if:fulfilment,mobile', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
            'website' => ['prohibited'],
        ];
    }
}
