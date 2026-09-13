<?php

namespace App\Modules\ServiceBooking\Enums;

enum ServiceRequestSource: string
{
    case Public = 'public';
    case AdminManual = 'admin_manual';
    case Legacy = 'legacy';
}
