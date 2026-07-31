<?php

namespace App\Http\Requests\Admin\Identity;

use Illuminate\Foundation\Http\FormRequest;

class AdminLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->normalizedEmail(),
        ]);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
        ];
    }

    public function normalizedEmail(): string
    {
        return mb_strtolower(trim((string) $this->input('email')));
    }
}
