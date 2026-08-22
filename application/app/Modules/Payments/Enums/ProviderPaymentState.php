<?php

namespace App\Modules\Payments\Enums;

enum ProviderPaymentState: string
{
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Paid = 'paid';
    case Voided = 'voided';
    case Failed = 'failed';
}
