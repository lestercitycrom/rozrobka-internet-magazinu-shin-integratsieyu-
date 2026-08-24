<?php

namespace App\Modules\Payments;

use App\Modules\Payments\Data\PaymentTransactionResult;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

final class AuthorizationExpiryPolicy
{
    public function expiresAt(PaymentTransactionResult $transaction, CarbonInterface $authorizedAt): CarbonImmutable
    {
        if (filled($transaction->authorizationExpiresAt)) {
            try {
                return CarbonImmutable::parse($transaction->authorizationExpiresAt);
            } catch (Throwable) {
                // Fail closed with the conservative fallback below.
            }
        }

        $product = mb_strtolower((string) $transaction->paymentProductType);
        $days = match (true) {
            str_contains($product, 'walley'), str_contains($product, 'collector') => 90,
            str_contains($product, 'klarna') => 28,
            default => 7,
        };

        return CarbonImmutable::instance($authorizedAt)->addDays($days);
    }
}
