<?php

namespace App\Modules\ServiceBooking;

final class CustomerPhoneNormalizer
{
    public function normalize(?string $phone): ?string
    {
        if (! filled($phone)) {
            return null;
        }

        $compact = preg_replace('/[\s().-]+/u', '', trim($phone));
        if (! is_string($compact) || $compact === '') {
            return null;
        }

        if (str_starts_with($compact, '00')) {
            $compact = '+'.substr($compact, 2);
        }
        if (! str_starts_with($compact, '+')) {
            $compact = '+47'.$compact;
        }

        $digits = preg_replace('/\D+/', '', $compact);

        return is_string($digits) && $digits !== '' ? $digits : null;
    }

    public function searchTerm(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);
        if (! is_string($digits) || strlen($digits) < 4) {
            return null;
        }

        return strlen($digits) === 8 ? '47'.$digits : $digits;
    }
}
