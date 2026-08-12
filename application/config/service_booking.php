<?php

return [
    /*
     * Public dates and administrator-entered calendar times are interpreted in
     * the shop's local timezone. Persisted slot timestamps remain in UTC.
     */
    'timezone' => env('SERVICE_BOOKING_TIMEZONE', 'Europe/Oslo'),
];
