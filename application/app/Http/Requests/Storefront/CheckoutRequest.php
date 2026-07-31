<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function rules(): array
    {
        return ['idempotency_key' => ['required', 'uuid'], 'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email'], 'phone' => ['required', 'string', 'max:64'], 'registration' => ['nullable', 'string', 'max:32'], 'note' => ['nullable', 'string', 'max:2000'], 'consent' => ['accepted'], 'terms' => ['accepted'], 'website' => ['prohibited']];
    }
}
