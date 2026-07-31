<?php

namespace App\Modules\NdiApi\Enums;

enum NdiAvailabilityStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Unavailable = 'unavailable';
    case Failed = 'failed';
}
