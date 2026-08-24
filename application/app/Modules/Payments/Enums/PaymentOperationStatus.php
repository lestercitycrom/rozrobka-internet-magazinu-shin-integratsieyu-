<?php

namespace App\Modules\Payments\Enums;

enum PaymentOperationStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Rejected = 'rejected';
}
