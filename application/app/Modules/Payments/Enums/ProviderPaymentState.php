<?php

namespace App\Modules\Payments\Enums;

enum ProviderPaymentState: string
{
    case Pending = 'pending';
    case Authorized = 'authorized';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Voided = 'voided';
    case Failed = 'failed';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
}
