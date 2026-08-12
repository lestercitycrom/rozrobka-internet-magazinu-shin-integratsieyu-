<?php

namespace App\Modules\ServiceBooking\Enums;

enum ServiceRequestStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
