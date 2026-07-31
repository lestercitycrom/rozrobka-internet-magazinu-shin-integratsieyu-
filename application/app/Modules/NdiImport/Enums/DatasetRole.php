<?php

namespace App\Modules\NdiImport\Enums;

enum DatasetRole: string
{
    case Tires = 'tires';
    case Wheels = 'wheels';
    case Stock = 'stock';
}
