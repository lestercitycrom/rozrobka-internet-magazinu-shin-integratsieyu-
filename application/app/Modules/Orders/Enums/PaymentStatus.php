<?php

namespace App\Modules\Orders\Enums;

enum PaymentStatus: string
{
    case NotStarted = 'not_started';
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Capturing = 'capturing';
    case Voiding = 'voiding';
    case Paid = 'paid';
    case Voided = 'voided';
    case Failed = 'failed';
    case Refunded = 'refunded';
}
