<?php

namespace App\Modules\Payments\Enums;

enum PaymentOperationType: string
{
    case Capture = 'capture';
    case Void = 'void';
    case Refund = 'refund';
}
