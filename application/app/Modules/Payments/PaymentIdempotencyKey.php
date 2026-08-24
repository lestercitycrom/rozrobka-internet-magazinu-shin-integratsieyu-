<?php

namespace App\Modules\Payments;

use App\Modules\Orders\Models\Order;

final class PaymentIdempotencyKey
{
    public static function authorizeFor(Order $order): string
    {
        return self::for($order, 'authorize');
    }

    public static function captureFor(Order $order): string
    {
        return self::for($order, 'capture');
    }

    public static function voidFor(Order $order): string
    {
        return self::for($order, 'void');
    }

    /** @param array<string, int|string> $quantities */
    public static function refundFor(Order $order, int $alreadyRefundedMinor = 0, array $quantities = []): string
    {
        ksort($quantities);

        return self::for($order, 'refund:'.$alreadyRefundedMinor.':'.hash('sha256', json_encode($quantities, JSON_THROW_ON_ERROR)));
    }

    private static function for(Order $order, string $operation): string
    {
        $hex = substr(hash('sha256', "easytire:payment:{$operation}:".$order->getKey()), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }
}
