<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class CustomerPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(__('phone_validation.invalid'));

            return;
        }

        $phone = trim($value);
        if ($phone === ''
            || preg_match('/[^0-9+\s().-]/u', $phone) === 1
            || substr_count($phone, '+') > 1
            || (str_contains($phone, '+') && ! str_starts_with($phone, '+'))) {
            $fail(__('phone_validation.invalid'));

            return;
        }

        $compact = preg_replace('/[\s().-]+/u', '', $phone);
        if (! is_string($compact) || $compact === '') {
            $fail(__('phone_validation.invalid'));

            return;
        }

        if (str_starts_with($compact, '00')) {
            $compact = '+'.substr($compact, 2);
        }

        if (str_starts_with($compact, '+47')) {
            $valid = preg_match('/^[2-9][0-9]{7}$/', substr($compact, 3)) === 1;
        } elseif (str_starts_with($compact, '+')) {
            $valid = preg_match('/^\+[1-9][0-9]{7,14}$/', $compact) === 1;
        } else {
            $valid = preg_match('/^[2-9][0-9]{7}$/', $compact) === 1;
        }

        if (! $valid) {
            $fail(__('phone_validation.invalid'));
        }
    }
}
