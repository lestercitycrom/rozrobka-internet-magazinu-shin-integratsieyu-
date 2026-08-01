<?php

namespace App\Modules\Payments;

use App\Modules\Payments\Exceptions\PaymentOperationRejected;
use App\Modules\Payments\Exceptions\PaymentsDisabled;

final class PaymentBoundaryGuard
{
    public static function assertEnabled(): void
    {
        if (! config('payments.enabled') && ! app()->runningUnitTests()) {
            throw new PaymentsDisabled('Payments remain disabled until a reviewed provider adapter is configured.');
        }
    }

    public static function provider(string $provider): string
    {
        if (preg_match('/^[a-z0-9][a-z0-9_-]{1,63}$/', $provider) !== 1 || $provider === 'disabled') {
            throw new PaymentOperationRejected('A configured payment provider is required.');
        }

        return $provider;
    }

    public static function idempotencyKey(string $key): string
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $key) !== 1) {
            throw new PaymentOperationRejected('A canonical UUID idempotency key is required.');
        }

        return strtolower($key);
    }

    public static function money(int $amountMinor, string $currency): string
    {
        $currency = strtoupper($currency);
        if ($amountMinor < 1 || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new PaymentOperationRejected('A positive amount and valid currency are required.');
        }

        return $currency;
    }

    public static function providerReference(string $reference): string
    {
        $reference = trim($reference);
        if ($reference === '' || mb_strlen($reference) > 255) {
            throw new PaymentOperationRejected('The provider returned an invalid payment reference.');
        }

        return $reference;
    }
}
