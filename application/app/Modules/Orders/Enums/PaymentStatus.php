<?php

namespace App\Modules\Orders\Enums;

enum PaymentStatus: string
{
    case NotStarted = 'not_started';
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Capturing = 'capturing';
    case PartiallyPaid = 'partially_paid';
    case Voiding = 'voiding';
    case Refunding = 'refunding';
    case Paid = 'paid';
    case Voided = 'voided';
    case Failed = 'failed';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
}
