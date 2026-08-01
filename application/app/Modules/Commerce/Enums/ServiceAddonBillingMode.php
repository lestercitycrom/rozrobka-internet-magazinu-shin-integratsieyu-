<?php

namespace App\Modules\Commerce\Enums;

enum ServiceAddonBillingMode: string
{
    case PerItem = 'per_item';
    case PerOrder = 'per_order';
}
