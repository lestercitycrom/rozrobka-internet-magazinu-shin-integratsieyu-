<?php

namespace App\Modules\ServiceBooking;

final class ServiceRequestMapLink
{
    public function forAddress(?string $address): ?string
    {
        $address = trim((string) $address);
        if ($address === '') {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address);
    }
}
