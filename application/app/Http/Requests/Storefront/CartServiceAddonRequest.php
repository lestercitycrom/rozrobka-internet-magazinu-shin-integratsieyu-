<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class CartServiceAddonRequest extends FormRequest
{
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9._-]{0,63}$/']];
    }
}
