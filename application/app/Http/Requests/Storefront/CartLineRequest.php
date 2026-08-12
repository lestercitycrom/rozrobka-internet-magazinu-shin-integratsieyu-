<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class CartLineRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'regex:/^[A-Za-z0-9._-]{1,80}$/'],
            'quantity' => ['required', 'integer', 'min:1', 'max:40'],
            'intent' => ['nullable', 'string', 'in:cart,checkout'],
        ];
    }
}
